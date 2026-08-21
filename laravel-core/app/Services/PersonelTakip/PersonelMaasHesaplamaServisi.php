<?php

namespace App\Services\PersonelTakip;

use App\Models\Personel\Personel;
use App\Models\Personel\PersonelAvansi;
use App\Models\Personel\PersonelGirisCikisi;
use App\Models\Personel\PersonelIzni;
use App\Models\Personel\PersonelMaasDonemi;
use App\Models\Personel\PersonelMaasHareketi;
use App\Models\Personel\PersonelMaasKalemi;
use App\Models\Scopes\FirmaIdTenantScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PersonelMaasHesaplamaServisi
{
    /**
     * @return array{hareket_sayisi:int, toplam_brut:float, toplam_kesinti:float, toplam_net:float}
     */
    public function donemiHesapla(PersonelMaasDonemi $donem): array
    {
        return DB::transaction(function () use ($donem): array {
            $donem = PersonelMaasDonemi::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->lockForUpdate()
                ->findOrFail($donem->getKey());

            if (! in_array((string) $donem->durum, ['taslak', 'hesaplandi'], true)) {
                throw ValidationException::withMessages([
                    'maas_donemi' => 'Onaylanmış veya ödenmiş maaş dönemi yeniden hesaplanamaz.',
                ]);
            }

            $ozet = [
                'hareket_sayisi' => 0,
                'toplam_brut' => 0.0,
                'toplam_kesinti' => 0.0,
                'toplam_net' => 0.0,
            ];

            $personeller = $this->donemPersonelleri($donem);
            foreach ($personeller as $personel) {
                $hesap = $this->personelHakEdisi($donem, $personel);
                $hareket = $this->hareketiKaydet($donem, $personel, $hesap);
                $this->kalemleriYenile($hareket, $hesap['kalemler']);

                $ozet['hareket_sayisi']++;
                $ozet['toplam_brut'] += $hesap['brut_tutar'];
                $ozet['toplam_kesinti'] += $hesap['avans_kesintisi'] + $hesap['devamsizlik_kesintisi'] + $hesap['diger_kesinti'];
                $ozet['toplam_net'] += $hareket->net_tutar;
            }

            $donem->forceFill([
                'toplam_brut' => round($ozet['toplam_brut'], 2),
                'toplam_kesinti' => round($ozet['toplam_kesinti'], 2),
                'toplam_net' => round($ozet['toplam_net'], 2),
                'durum' => $donem->durum === 'taslak' ? 'hesaplandi' : $donem->durum,
            ])->save();

            return [
                'hareket_sayisi' => $ozet['hareket_sayisi'],
                'toplam_brut' => round($ozet['toplam_brut'], 2),
                'toplam_kesinti' => round($ozet['toplam_kesinti'], 2),
                'toplam_net' => round($ozet['toplam_net'], 2),
            ];
        });
    }

    /** @return Collection<int, Personel> */
    private function donemPersonelleri(PersonelMaasDonemi $donem): Collection
    {
        $baslangic = Carbon::parse($donem->baslangic_tarihi)->toDateString();
        $bitis = Carbon::parse($donem->bitis_tarihi)->toDateString();

        return Personel::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $donem->firma_id)
            ->when($donem->sube_id, fn ($query) => $query->where('sube_id', $donem->sube_id))
            ->where(function ($query) use ($bitis): void {
                $query->whereNull('ise_giris_tarihi')
                    ->orWhereDate('ise_giris_tarihi', '<=', $bitis);
            })
            ->where(function ($query) use ($baslangic): void {
                $query->whereNull('isten_cikis_tarihi')
                    ->orWhereDate('isten_cikis_tarihi', '>=', $baslangic);
            })
            ->where('durum', '!=', Personel::DURUM_PASIF)
            ->orderBy('ad_soyad')
            ->get();
    }

    /**
     * @return array{
     *     brut_tutar:float,
     *     fazla_mesai_tutari:float,
     *     prim_tutari:float,
     *     ek_odeme_tutari:float,
     *     avans_kesintisi:float,
     *     devamsizlik_kesintisi:float,
     *     diger_kesinti:float,
     *     kalemler:array<int, array{kalem_turu:string, aciklama:string, tutar:float}>
     * }
     */
    private function personelHakEdisi(PersonelMaasDonemi $donem, Personel $personel): array
    {
        $ayarlar = app(PersonelAyarlariServisi::class)->genel((int) $donem->firma_id);
        $girisCikislari = $this->girisCikislari($donem, $personel);
        $calisilanDakika = (int) $girisCikislari->sum(function (PersonelGirisCikisi $kayit): int {
            if (! $kayit->giris_at || ! $kayit->cikis_at) {
                return 0;
            }

            return max(0, Carbon::parse($kayit->giris_at)->diffInMinutes(Carbon::parse($kayit->cikis_at)));
        });
        $calisilanGun = $girisCikislari
            ->map(fn (PersonelGirisCikisi $kayit): ?string => $kayit->tarih?->toDateString() ?: $kayit->giris_at?->toDateString())
            ->filter()
            ->unique()
            ->count();

        $brut = match ($personel->maas_tipi) {
            'gunluk' => round((float) ($personel->gunluk_ucret ?: $personel->maas_tutari) * $calisilanGun, 2),
            'saatlik' => round((float) ($personel->saatlik_ucret ?: 0) * ($calisilanDakika / 60), 2),
            default => (float) $personel->maas_tutari,
        };

        $fazlaMesaiDakika = (int) $girisCikislari->sum('fazla_mesai_dakika');
        $saatlikUcret = (float) ($personel->saatlik_ucret ?: ($personel->maas_tutari > 0 ? $personel->maas_tutari / 225 : 0));
        $fazlaMesaiKatsayi = max(1, (float) ($ayarlar['fazla_mesai_katsayi'] ?? 1.5));
        $fazlaMesai = round(($fazlaMesaiDakika / 60) * $saatlikUcret * $fazlaMesaiKatsayi, 2);
        $avansKesintisi = $this->avansKesintisi($donem, $personel);
        $devamsizlikKesintisi = $this->devamsizlikKesintisi($donem, $personel);

        $kalemler = [
            ['kalem_turu' => 'sabit_maas', 'aciklama' => 'Dönem hakedişi', 'tutar' => $brut],
        ];

        if ($fazlaMesai > 0) {
            $kalemler[] = ['kalem_turu' => 'fazla_mesai', 'aciklama' => 'Onaylı giriş-çıkış fazla mesaisi', 'tutar' => $fazlaMesai];
        }

        if ($avansKesintisi > 0) {
            $kalemler[] = ['kalem_turu' => 'avans_kesintisi', 'aciklama' => 'Maaştan düşülecek avans', 'tutar' => -1 * $avansKesintisi];
        }

        if ($devamsizlikKesintisi > 0) {
            $kalemler[] = ['kalem_turu' => 'devamsizlik_kesintisi', 'aciklama' => 'Onaylı devamsızlık kesintisi', 'tutar' => -1 * $devamsizlikKesintisi];
        }

        return [
            'brut_tutar' => $brut,
            'fazla_mesai_tutari' => $fazlaMesai,
            'prim_tutari' => 0.0,
            'ek_odeme_tutari' => 0.0,
            'avans_kesintisi' => $avansKesintisi,
            'devamsizlik_kesintisi' => $devamsizlikKesintisi,
            'diger_kesinti' => 0.0,
            'kalemler' => $kalemler,
        ];
    }

    /** @return Collection<int, PersonelGirisCikisi> */
    private function girisCikislari(PersonelMaasDonemi $donem, Personel $personel): Collection
    {
        return PersonelGirisCikisi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $donem->firma_id)
            ->where('personel_id', $personel->id)
            ->where('onay_durumu', 'onaylandi')
            ->whereBetween('giris_at', [
                Carbon::parse($donem->baslangic_tarihi)->startOfDay(),
                Carbon::parse($donem->bitis_tarihi)->endOfDay(),
            ])
            ->get();
    }

    private function avansKesintisi(PersonelMaasDonemi $donem, Personel $personel): float
    {
        return (float) PersonelAvansi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $donem->firma_id)
            ->where('personel_id', $personel->id)
            ->where('durum', 'onaylandi')
            ->where('maastan_dusuldu_mu', false)
            ->whereBetween('tarih', [
                Carbon::parse($donem->baslangic_tarihi)->toDateString(),
                Carbon::parse($donem->bitis_tarihi)->toDateString(),
            ])
            ->sum('kalan_tutar');
    }

    private function devamsizlikKesintisi(PersonelMaasDonemi $donem, Personel $personel): float
    {
        if (! in_array((string) $personel->maas_tipi, ['aylik', 'karma'], true) || (float) $personel->maas_tutari <= 0) {
            return 0.0;
        }

        $gunSayisi = (float) PersonelIzni::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $donem->firma_id)
            ->where('personel_id', $personel->id)
            ->where('izin_turu', 'devamsizlik')
            ->where(function ($query): void {
                $query->where('durum', 'onaylandi')
                    ->orWhere('onay_durumu', 'onaylandi');
            })
            ->whereBetween('baslangic_at', [
                Carbon::parse($donem->baslangic_tarihi)->startOfDay(),
                Carbon::parse($donem->bitis_tarihi)->endOfDay(),
            ])
            ->sum('gun_sayisi');

        if ($gunSayisi <= 0) {
            return 0.0;
        }

        return round(((float) $personel->maas_tutari / 30) * $gunSayisi, 2);
    }

    /**
     * @param array{
     *     brut_tutar:float,
     *     fazla_mesai_tutari:float,
     *     prim_tutari:float,
     *     ek_odeme_tutari:float,
     *     avans_kesintisi:float,
     *     devamsizlik_kesintisi:float,
     *     diger_kesinti:float
     * } $hesap
     */
    private function hareketiKaydet(PersonelMaasDonemi $donem, Personel $personel, array $hesap): PersonelMaasHareketi
    {
        $hareket = PersonelMaasHareketi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->firstOrNew([
                'firma_id' => $donem->firma_id,
                'maas_donemi_id' => $donem->id,
                'personel_id' => $personel->id,
            ]);

        $hareket->fill([
            'brut_tutar' => $hesap['brut_tutar'],
            'fazla_mesai_tutari' => $hesap['fazla_mesai_tutari'],
            'prim_tutari' => $hesap['prim_tutari'],
            'ek_odeme_tutari' => $hesap['ek_odeme_tutari'],
            'avans_kesintisi' => $hesap['avans_kesintisi'],
            'devamsizlik_kesintisi' => $hesap['devamsizlik_kesintisi'],
            'diger_kesinti' => $hesap['diger_kesinti'],
            'durum' => $hareket->exists ? $hareket->durum : 'taslak',
        ]);
        $hareket->save();

        return $hareket;
    }

    /**
     * @param array<int, array{kalem_turu:string, aciklama:string, tutar:float}> $kalemler
     */
    private function kalemleriYenile(PersonelMaasHareketi $hareket, array $kalemler): void
    {
        PersonelMaasKalemi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $hareket->firma_id)
            ->where('maas_hareketi_id', $hareket->id)
            ->delete();

        foreach ($kalemler as $kalem) {
            if ((float) $kalem['tutar'] === 0.0) {
                continue;
            }

            PersonelMaasKalemi::query()->create([
                'firma_id' => $hareket->firma_id,
                'maas_hareketi_id' => $hareket->id,
                'kalem_turu' => $kalem['kalem_turu'],
                'aciklama' => $kalem['aciklama'],
                'tutar' => $kalem['tutar'],
            ]);
        }
    }
}
