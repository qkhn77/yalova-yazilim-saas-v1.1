<?php

namespace App\Services\Restoran;

use App\Models\Muhasebe\BankaHareketi;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\KasaHareketi;
use App\Models\Muhasebe\PosHareketi;
use App\Models\Restoran\RestoranAdisyonTahsilati;
use App\Models\Restoran\RestoranGunSonuKapanisi;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Muhasebe\Enumlar\FinansHareketDurumu;
use App\Muhasebe\Enumlar\HareketDurumu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class RestoranGunSonuMutabakatServisi
{
    /**
     * @return array{tarih:string,toplam_tahsilat:float,toplam_muhasebe:float,toplam_fark:float,mutabik_mi:bool,kanallar:array<int,array<string,mixed>>}
     */
    public function gunlukOzet(int $firmaId, Carbon|string $tarih): array
    {
        $gun = Carbon::parse($tarih);
        $baslangic = $gun->copy()->startOfDay();
        $bitis = $gun->copy()->endOfDay();
        $kanallar = [];
        $tahsilatOzetleri = $this->tahsilatOzetleri($firmaId, $baslangic, $bitis);

        foreach (['kasa', 'banka', 'pos'] as $kanal) {
            $tahsilat = $tahsilatOzetleri[$kanal] ?? ['adet' => 0, 'tutar' => 0.0];
            $muhasebe = $this->muhasebeOzeti($firmaId, $kanal, $baslangic, $bitis);
            $fark = round($tahsilat['tutar'] - $muhasebe['tutar'], 2);

            $kanallar[] = [
                'kanal' => $kanal,
                'kanal_etiketi' => $this->kanalEtiketi($kanal),
                'tahsilat_sayisi' => $tahsilat['adet'],
                'tahsilat_tutari' => $tahsilat['tutar'],
                'muhasebe_sayisi' => $muhasebe['adet'],
                'muhasebe_tutari' => $muhasebe['tutar'],
                'fark' => $fark,
                'mutabik_mi' => abs($fark) < 0.01,
            ];
        }

        $toplamTahsilat = round(array_sum(array_column($kanallar, 'tahsilat_tutari')), 2);
        $toplamMuhasebe = round(array_sum(array_column($kanallar, 'muhasebe_tutari')), 2);
        $toplamFark = round($toplamTahsilat - $toplamMuhasebe, 2);

        return [
            'tarih' => $gun->toDateString(),
            'toplam_tahsilat' => $toplamTahsilat,
            'toplam_muhasebe' => $toplamMuhasebe,
            'toplam_fark' => $toplamFark,
            'mutabik_mi' => abs($toplamFark) < 0.01 && collect($kanallar)->every(fn (array $satir): bool => (bool) $satir['mutabik_mi']),
            'kanallar' => $kanallar,
            'kapanis' => $this->kapanisKaydi($firmaId, $gun),
        ];
    }

    public function kapanisKaydet(
        int $firmaId,
        Carbon|string $tarih,
        ?string $farkAciklamasi = null,
        ?string $notlar = null,
        ?int $kapatanId = null
    ): RestoranGunSonuKapanisi {
        $ozet = $this->gunlukOzet($firmaId, $tarih);

        $farkAciklamasi = $this->metinTemizle($farkAciklamasi, 500, 'fark_aciklamasi');
        $notlar = $this->metinTemizle($notlar, 1000, 'notlar');

        if (! (bool) $ozet['mutabik_mi'] && $farkAciklamasi === null) {
            throw ValidationException::withMessages([
                'fark_aciklamasi' => 'Fark bulunan gün sonu kapanışında açıklama zorunludur.',
            ]);
        }

        $kapanis = RestoranGunSonuKapanisi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereDate('tarih', $ozet['tarih'])
            ->first();

        if (! $kapanis) {
            $kapanis = new RestoranGunSonuKapanisi([
                'firma_id' => $firmaId,
                'tarih' => $ozet['tarih'],
            ]);
        }

        $kapanis->forceFill([
            'toplam_tahsilat' => $ozet['toplam_tahsilat'],
            'toplam_muhasebe' => $ozet['toplam_muhasebe'],
            'toplam_fark' => $ozet['toplam_fark'],
            'mutabik_mi' => $ozet['mutabik_mi'],
            'kanal_ozeti' => $ozet['kanallar'],
            'fark_aciklamasi' => $farkAciklamasi,
            'notlar' => $notlar,
            'kapatan_id' => $kapatanId,
            'kapandi_at' => now(),
        ])->save();

        return $kapanis->refresh();
    }

    public function kapanisKaydi(int $firmaId, Carbon|string $tarih): ?RestoranGunSonuKapanisi
    {
        return RestoranGunSonuKapanisi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereDate('tarih', Carbon::parse($tarih)->toDateString())
            ->first();
    }

    /**
     * @return array<string, array{adet:int,tutar:float}>
     */
    private function tahsilatOzetleri(int $firmaId, Carbon $baslangic, Carbon $bitis): array
    {
        return RestoranAdisyonTahsilati::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->where('durum', RestoranAdisyonTahsilati::DURUM_AKTIF)
            ->whereIn('odeme_kanali', ['kasa', 'banka', 'pos'])
            ->whereBetween('tahsilat_at', [$baslangic, $bitis])
            ->groupBy('odeme_kanali')
            ->select('odeme_kanali')
            ->selectRaw('COUNT(*) as adet')
            ->selectRaw('COALESCE(SUM(tutar), 0) as tutar')
            ->get()
            ->mapWithKeys(fn ($satir): array => [
                (string) $satir->odeme_kanali => [
                    'adet' => (int) ($satir->adet ?? 0),
                    'tutar' => round((float) ($satir->tutar ?? 0), 2),
                ],
            ])
            ->all();
    }

    /**
     * @return array{adet:int,tutar:float}
     */
    private function muhasebeOzeti(int $firmaId, string $kanal, Carbon $baslangic, Carbon $bitis): array
    {
        $model = match ($kanal) {
            'kasa' => KasaHareketi::class,
            'banka' => BankaHareketi::class,
            'pos' => PosHareketi::class,
            default => KasaHareketi::class,
        };

        $satir = $model::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->where('durum', HareketDurumu::Aktif)
            ->whereHas('finansHareketi', function (Builder $query) use ($firmaId, $baslangic, $bitis): void {
                $query
                    ->withoutGlobalScope(FirmaIdTenantScope::class)
                    ->where('firma_id', $firmaId)
                    ->where('durum', FinansHareketDurumu::Aktif)
                    ->where('referans_turu', 'restoran_adisyon')
                    ->whereBetween('tarih', [$baslangic, $bitis]);
            })
            ->selectRaw('COUNT(*) as adet')
            ->selectRaw('COALESCE(SUM(tutar), 0) as tutar')
            ->first();

        return [
            'adet' => (int) ($satir?->adet ?? 0),
            'tutar' => round((float) ($satir?->tutar ?? 0), 2),
        ];
    }

    private function kanalEtiketi(string $kanal): string
    {
        return match ($kanal) {
            'kasa' => 'Kasa',
            'banka' => 'Banka',
            'pos' => 'POS',
            default => $kanal,
        };
    }

    private function metinTemizle(?string $deger, int $maksimum, string $alan): ?string
    {
        $deger = trim((string) $deger);
        if ($deger === '') {
            return null;
        }

        if (mb_strlen($deger) > $maksimum) {
            throw ValidationException::withMessages([
                $alan => 'Bu alan en fazla '.$maksimum.' karakter olabilir.',
            ]);
        }

        return $deger;
    }
}
