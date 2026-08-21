<?php

namespace App\Services\Restoran;

use App\Models\Muhasebe\StokHareketi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\Depo;
use App\Models\Restoran\RestoranAdisyonKalemi;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Restoran\RestoranRecetesi;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Muhasebe\Enumlar\StokBelgeTuru;
use App\Muhasebe\Enumlar\StokHareketDurumu;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;
use App\Muhasebe\Servisler\StokHareketServisi;
use App\Services\FirmaAyarDeposu;
use Illuminate\Validation\ValidationException;

final class RestoranStokServisi
{
    public function __construct(
        private readonly StokHareketServisi $stokHareketServisi,
        private readonly FirmaAyarDeposu $firmaAyarDeposu,
    ) {}

    public function adisyonStokHareketleriniOlustur(RestoranAdisyonu $adisyon): void
    {
        if ($this->adisyonIcinStokHareketiVarMi($adisyon)) {
            return;
        }

        $this->stokYeterliliginiDogrula($adisyon);

        $stokCikislari = $this->stokCikislariHazirla($adisyon);

        foreach ($stokCikislari as $stokId => $cikis) {
            $miktar = round((float) $cikis['miktar'], 4);
            if ($miktar <= 0) {
                continue;
            }

            $toplam = round((float) $cikis['toplam'], 2);
            $birimFiyat = $miktar > 0 ? round($toplam / $miktar, 2) : 0;

            $this->stokHareketServisi->kayitOlustur((int) $adisyon->firma_id, [
                'stok_id' => (int) $stokId,
                'depo_id' => $this->varsayilanDepoId((int) $adisyon->firma_id),
                'cari_id' => $adisyon->cari_id,
                'islem_turu' => StokHareketIslemTuru::Satis,
                'miktar' => $miktar,
                'birim_fiyat' => $birimFiyat,
                'toplam' => $toplam,
                'belge_turu' => StokBelgeTuru::RestoranAdisyon,
                'belge_id' => (int) $adisyon->id,
                'referans_tipi' => StokBelgeTuru::RestoranAdisyon->value,
                'referans_id' => (int) $adisyon->id,
                'aciklama' => 'Restoran adisyon stok cikisi: '.$adisyon->adisyon_no,
                'tarih' => $adisyon->tahsilat_at ?: now(),
            ]);
        }
    }

    public function stokYeterliliginiDogrula(RestoranAdisyonu $adisyon): void
    {
        if ((bool) config('muhasebe.stok.negatif_stok_izinli', false)
            || (bool) $this->firmaAyarDeposu->oku((int) $adisyon->firma_id, 'negatif_stok_izinli', false)) {
            return;
        }

        $stokCikislari = $this->stokCikislariHazirla($adisyon);
        if ($stokCikislari === []) {
            return;
        }

        $stoklar = StokKarti::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $adisyon->firma_id)
            ->whereIn('id', array_keys($stokCikislari))
            ->get(['id', 'ad', 'stok_takip', 'stok_miktari', 'rezerve_miktar'])
            ->keyBy('id');

        $hatalar = [];
        foreach ($stokCikislari as $stokId => $cikis) {
            $stok = $stoklar->get($stokId);
            if (! $stok) {
                $hatalar[] = 'Restoran reçetesinde firma stoğunda bulunmayan kalem var: #'.$stokId;

                continue;
            }

            if (! (bool) $stok->stok_takip) {
                continue;
            }

            $gereken = round((float) $cikis['miktar'], 4);
            $musait = round($stok->musaitStokMiktari(), 4);
            if ($musait + 0.0001 >= $gereken) {
                continue;
            }

            $hatalar[] = sprintf(
                'Yetersiz stok: %s. Gereken: %.4f, müsait: %.4f.',
                (string) $stok->ad,
                $gereken,
                $musait
            );
        }

        if ($hatalar !== []) {
            throw ValidationException::withMessages([
                'stok' => $hatalar,
            ]);
        }
    }

    private function varsayilanDepoId(int $firmaId): ?int
    {
        $ayar = app(FirmaAyarDeposu::class);
        if (! (bool) $ayar->oku($firmaId, 'stok_depo_modulu_aktif_mi', false)) {
            return null;
        }

        $depoId = (int) ($ayar->oku($firmaId, 'stok_varsayilan_depo_id', 0) ?? 0);
        if ($depoId > 0 && Depo::tenantScopeOlmadan(fn () => Depo::query()
            ->where('firma_id', $firmaId)
            ->whereKey($depoId)
            ->where('aktif_mi', true)
            ->exists())) {
            return $depoId;
        }

        return Depo::tenantScopeOlmadan(fn () => Depo::query()
            ->where('firma_id', $firmaId)
            ->where('aktif_mi', true)
            ->where('varsayilan_mi', true)
            ->value('id'));
    }

    public function adisyonStokHareketleriniTersle(RestoranAdisyonu $adisyon, ?string $aciklama = null): void
    {
        $hareketler = StokHareketi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $adisyon->firma_id)
            ->where('belge_turu', StokBelgeTuru::RestoranAdisyon->value)
            ->where('belge_id', $adisyon->id)
            ->where('durum', StokHareketDurumu::Aktif)
            ->get();

        foreach ($hareketler as $hareket) {
            if ($this->stokHareketiTerslenmisMi($hareket)) {
                continue;
            }

            $this->stokHareketServisi->tersKayitOlustur(
                $hareket,
                $aciklama ?: 'Restoran adisyon stok iadesi: '.$adisyon->adisyon_no
            );
        }
    }

    /**
     * @return array<int, array{miktar:float, toplam:float}>
     */
    private function stokCikislariHazirla(RestoranAdisyonu $adisyon): array
    {
        $kalemler = RestoranAdisyonKalemi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $adisyon->firma_id)
            ->where('adisyon_id', $adisyon->id)
            ->where('durum', '!=', RestoranAdisyonKalemi::DURUM_IPTAL)
            ->get(['id', 'firma_id', 'menu_urunu_id', 'stok_karti_id', 'miktar', 'toplam_tutar']);

        $menuUrunuIdleri = $kalemler
            ->pluck('menu_urunu_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        $receteler = RestoranRecetesi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->with(['kalemler' => fn ($query) => $query->withoutGlobalScope(FirmaIdTenantScope::class)])
            ->where('firma_id', $adisyon->firma_id)
            ->where('aktif_mi', true)
            ->whereIn('menu_urunu_id', $menuUrunuIdleri)
            ->get();

        $receteHaritasi = $receteler->keyBy('menu_urunu_id');
        $stokCikislari = [];

        foreach ($kalemler as $kalem) {
            $recete = $kalem->menu_urunu_id ? $receteHaritasi->get((int) $kalem->menu_urunu_id) : null;

            if ($recete && $recete->kalemler->isNotEmpty()) {
                foreach ($recete->kalemler as $receteKalemi) {
                    $miktar = (float) $kalem->miktar * (float) $receteKalemi->miktar;
                    $fireCarpani = 1 + ((float) $receteKalemi->fire_orani / 100);
                    $this->stokCikisiEkle(
                        $stokCikislari,
                        (int) $receteKalemi->stok_karti_id,
                        $miktar * $fireCarpani,
                        0
                    );
                }

                continue;
            }

            if ($kalem->stok_karti_id) {
                $this->stokCikisiEkle(
                    $stokCikislari,
                    (int) $kalem->stok_karti_id,
                    (float) $kalem->miktar,
                    (float) $kalem->toplam_tutar
                );
            }
        }

        if ($stokCikislari === []) {
            return [];
        }

        $stoklar = StokKarti::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $adisyon->firma_id)
            ->whereIn('id', array_keys($stokCikislari))
            ->get(['id', 'guncel_birim_maliyet'])
            ->keyBy('id');

        foreach ($stokCikislari as $stokId => &$cikis) {
            if ($cikis['toplam'] > 0) {
                continue;
            }

            $birimMaliyet = (float) ($stoklar->get($stokId)?->guncel_birim_maliyet ?? 0);
            $cikis['toplam'] = round($cikis['miktar'] * $birimMaliyet, 2);
        }
        unset($cikis);

        return $stokCikislari;
    }

    /**
     * @param  array<int, array{miktar:float, toplam:float}>  $stokCikislari
     */
    private function stokCikisiEkle(array &$stokCikislari, int $stokId, float $miktar, float $toplam): void
    {
        if ($miktar <= 0) {
            return;
        }

        if (! array_key_exists($stokId, $stokCikislari)) {
            $stokCikislari[$stokId] = ['miktar' => 0, 'toplam' => 0];
        }

        $stokCikislari[$stokId]['miktar'] += $miktar;
        $stokCikislari[$stokId]['toplam'] += max(0, $toplam);
    }

    private function adisyonIcinStokHareketiVarMi(RestoranAdisyonu $adisyon): bool
    {
        return StokHareketi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $adisyon->firma_id)
            ->where('belge_turu', StokBelgeTuru::RestoranAdisyon->value)
            ->where('belge_id', $adisyon->id)
            ->exists();
    }

    private function stokHareketiTerslenmisMi(StokHareketi $hareket): bool
    {
        return StokHareketi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $hareket->firma_id)
            ->where('iptal_edilen_hareket_id', $hareket->id)
            ->exists();
    }
}
