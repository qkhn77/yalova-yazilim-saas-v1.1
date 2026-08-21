<?php

namespace App\Services\PersonelTakip;

use App\Models\Personel\Personel;
use App\Models\Personel\PersonelAvansi;
use App\Models\Personel\PersonelGirisCikisi;
use App\Models\Personel\PersonelIzni;
use App\Models\Personel\PersonelMaasHareketi;
use App\Models\Personel\PersonelVardiyasi;
use App\Models\Restoran\RestoranAdisyonKalemi;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Models\TeknikServis\TeknikServisGorevAtamasi;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class PersonelRaporServisi
{
    /**
     * @return array<string, mixed>
     */
    public function ozet(int $firmaId, ?string $baslangic = null, ?string $bitis = null, ?int $subeId = null): array
    {
        $bas = $baslangic ? CarbonImmutable::parse($baslangic)->startOfDay() : CarbonImmutable::now()->startOfMonth();
        $son = $bitis ? CarbonImmutable::parse($bitis)->endOfDay() : CarbonImmutable::now()->endOfDay();

        if ($son->lt($bas)) {
            [$bas, $son] = [$son->startOfDay(), $bas->endOfDay()];
        }

        $aktifPersonel = Personel::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->when($subeId, fn ($query) => $query->where('sube_id', $subeId))
            ->where('durum', Personel::DURUM_AKTIF);

        $vardiyalar = PersonelVardiyasi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->when($subeId, fn ($query) => $query->where('sube_id', $subeId))
            ->whereBetween('tarih', [$bas->toDateString(), $son->toDateString()])
            ->where('durum', '!=', 'iptal')
            ->get(['id', 'personel_id', 'baslangic_at', 'bitis_at', 'mola_dakika']);

        $girisCikislari = PersonelGirisCikisi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->when($subeId, fn ($query) => $query->where('sube_id', $subeId))
            ->whereBetween('tarih', [$bas->toDateString(), $son->toDateString()])
            ->where('onay_durumu', 'onaylandi')
            ->get(['id', 'personel_id', 'giris_at', 'cikis_at', 'gec_kalma_dakika', 'erken_cikis_dakika', 'fazla_mesai_dakika', 'eksik_calisma_dakika']);

        $izinler = PersonelIzni::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->where('onay_durumu', 'onaylandi')
            ->where('baslangic_at', '<=', $son)
            ->where('bitis_at', '>=', $bas)
            ->when($subeId, function ($query) use ($subeId): void {
                $query->whereHas('personel', fn ($personel) => $personel
                    ->withoutGlobalScope(FirmaIdTenantScope::class)
                    ->where('sube_id', $subeId));
            })
            ->get(['id', 'personel_id', 'gun_sayisi', 'saat_sayisi']);

        $avansToplami = (float) PersonelAvansi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->when($subeId, function ($query) use ($subeId): void {
                $query->whereHas('personel', fn ($personel) => $personel
                    ->withoutGlobalScope(FirmaIdTenantScope::class)
                    ->where('sube_id', $subeId));
            })
            ->whereBetween('tarih', [$bas->toDateString(), $son->toDateString()])
            ->whereIn('onay_durumu', ['onaylandi', 'bekliyor'])
            ->sum('kalan_tutar');

        $maaslar = PersonelMaasHareketi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereHas('donem', function ($query) use ($bas, $son, $subeId): void {
                $query->withoutGlobalScope(FirmaIdTenantScope::class)
                    ->where('baslangic_tarihi', '<=', $son->toDateString())
                    ->where('bitis_tarihi', '>=', $bas->toDateString())
                    ->when($subeId, fn ($donem) => $donem->where('sube_id', $subeId));
            })
            ->get(['id', 'personel_id', 'net_tutar', 'odenen_tutar', 'kalan_tutar']);

        $restoran = $this->restoranPerformansi($firmaId, $bas, $son, $subeId);
        $teknikServis = $this->teknikServisPerformansi($firmaId, $bas, $son, $subeId);

        return [
            'filtre' => [
                'firma_id' => $firmaId,
                'sube_id' => $subeId,
                'baslangic' => $bas->toDateString(),
                'bitis' => $son->toDateString(),
            ],
            'kpi' => [
                'aktif_personel' => (clone $aktifPersonel)->count(),
                'planli_vardiya' => $vardiyalar->count(),
                'planli_calisma_dakika' => $this->planliCalismaDakikasi($vardiyalar),
                'onayli_giris_cikis' => $girisCikislari->count(),
                'fiili_calisma_dakika' => $this->fiiliCalismaDakikasi($girisCikislari),
                'gec_kalma_dakika' => (int) $girisCikislari->sum('gec_kalma_dakika'),
                'erken_cikis_dakika' => (int) $girisCikislari->sum('erken_cikis_dakika'),
                'fazla_mesai_dakika' => (int) $girisCikislari->sum('fazla_mesai_dakika'),
                'eksik_calisma_dakika' => (int) $girisCikislari->sum('eksik_calisma_dakika'),
                'izin_gun' => round((float) $izinler->sum('gun_sayisi'), 2),
                'izin_saat' => round((float) $izinler->sum('saat_sayisi'), 2),
                'acik_avans' => round($avansToplami, 2),
                'maas_net' => round((float) $maaslar->sum('net_tutar'), 2),
                'maas_odenen' => round((float) $maaslar->sum('odenen_tutar'), 2),
                'maas_kalan' => round((float) $maaslar->sum('kalan_tutar'), 2),
                'restoran_adisyon' => $restoran['adisyon_sayisi'],
                'restoran_ciro' => $restoran['ciro'],
                'restoran_mutfak_kalem' => $restoran['mutfak_kalem_sayisi'],
                'teknik_servis_gorev' => $teknikServis['gorev_sayisi'],
                'teknik_servis_tamamlanan_gorev' => $teknikServis['tamamlanan_gorev_sayisi'],
            ],
            'personel_performansi' => $this->personelPerformansi($firmaId, $girisCikislari),
            'restoran_performansi' => $restoran,
            'teknik_servis_performansi' => $teknikServis,
        ];
    }

    /** @param Collection<int, PersonelVardiyasi> $vardiyalar */
    private function planliCalismaDakikasi(Collection $vardiyalar): int
    {
        return (int) $vardiyalar->sum(function (PersonelVardiyasi $vardiya): int {
            if (! $vardiya->baslangic_at || ! $vardiya->bitis_at) {
                return 0;
            }

            $dakika = $vardiya->baslangic_at->diffInMinutes($vardiya->bitis_at, false);

            return max(0, $dakika - (int) $vardiya->mola_dakika);
        });
    }

    /** @param Collection<int, PersonelGirisCikisi> $girisCikislari */
    private function fiiliCalismaDakikasi(Collection $girisCikislari): int
    {
        return (int) $girisCikislari->sum(function (PersonelGirisCikisi $kayit): int {
            if (! $kayit->giris_at || ! $kayit->cikis_at) {
                return 0;
            }

            return max(0, $kayit->giris_at->diffInMinutes($kayit->cikis_at, false));
        });
    }

    /**
     * @param Collection<int, PersonelGirisCikisi> $girisCikislari
     * @return list<array<string, mixed>>
     */
    private function personelPerformansi(int $firmaId, Collection $girisCikislari): array
    {
        $personelAdlari = $this->personelAdlari($firmaId, $girisCikislari->pluck('personel_id')->unique()->values());

        return $girisCikislari
            ->groupBy('personel_id')
            ->map(function (Collection $satirlar, int $personelId) use ($personelAdlari): array {
                return [
                    'personel_id' => $personelId,
                    'ad_soyad' => (string) ($personelAdlari[$personelId] ?? '#'.$personelId),
                    'giris_cikis_sayisi' => $satirlar->count(),
                    'calisma_dakika' => $this->fiiliCalismaDakikasi($satirlar),
                    'fazla_mesai_dakika' => (int) $satirlar->sum('fazla_mesai_dakika'),
                    'gec_kalma_dakika' => (int) $satirlar->sum('gec_kalma_dakika'),
                ];
            })
            ->sortByDesc('calisma_dakika')
            ->values()
            ->take(10)
            ->all();
    }

    /**
     * @return array{
     *     adisyon_sayisi:int,
     *     ciro:float,
     *     mutfak_kalem_sayisi:int,
     *     garsonlar:list<array<string, mixed>>,
     *     kasiyerler:list<array<string, mixed>>,
     *     mutfak:list<array<string, mixed>>
     * }
     */
    private function restoranPerformansi(int $firmaId, CarbonImmutable $bas, CarbonImmutable $son, ?int $subeId): array
    {
        $adisyonlar = RestoranAdisyonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->when($subeId, fn ($query) => $query->where('sube_id', $subeId))
            ->where('durum', '!=', RestoranAdisyonu::DURUM_IPTAL)
            ->whereBetween('acilis_at', [$bas, $son])
            ->get(['id', 'garson_personel_id', 'kasiyer_personel_id', 'genel_toplam']);

        $kalemler = RestoranAdisyonKalemi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereNotNull('hazirlayan_personel_id')
            ->where('durum', '!=', RestoranAdisyonKalemi::DURUM_IPTAL)
            ->whereHas('adisyon', function ($query) use ($firmaId, $bas, $son, $subeId): void {
                $query->withoutGlobalScope(FirmaIdTenantScope::class)
                    ->where('firma_id', $firmaId)
                    ->when($subeId, fn ($adisyon) => $adisyon->where('sube_id', $subeId))
                    ->whereBetween('acilis_at', [$bas, $son]);
            })
            ->get(['id', 'hazirlayan_personel_id', 'miktar', 'toplam_tutar']);

        $personelIds = $adisyonlar->pluck('garson_personel_id')
            ->merge($adisyonlar->pluck('kasiyer_personel_id'))
            ->merge($kalemler->pluck('hazirlayan_personel_id'))
            ->filter()
            ->unique()
            ->values();
        $adlar = $this->personelAdlari($firmaId, $personelIds);

        return [
            'adisyon_sayisi' => $adisyonlar->count(),
            'ciro' => round((float) $adisyonlar->sum('genel_toplam'), 2),
            'mutfak_kalem_sayisi' => $kalemler->count(),
            'garsonlar' => $this->restoranAdisyonPersonelOzeti($adisyonlar, 'garson_personel_id', $adlar),
            'kasiyerler' => $this->restoranAdisyonPersonelOzeti($adisyonlar, 'kasiyer_personel_id', $adlar),
            'mutfak' => $kalemler
                ->groupBy('hazirlayan_personel_id')
                ->map(fn (Collection $satirlar, int $personelId): array => [
                    'personel_id' => $personelId,
                    'ad_soyad' => (string) ($adlar[$personelId] ?? '#'.$personelId),
                    'kalem_sayisi' => $satirlar->count(),
                    'miktar' => round((float) $satirlar->sum('miktar'), 4),
                    'toplam_tutar' => round((float) $satirlar->sum('toplam_tutar'), 2),
                ])
                ->sortByDesc('kalem_sayisi')
                ->values()
                ->take(10)
                ->all(),
        ];
    }

    /**
     * @param Collection<int, RestoranAdisyonu> $adisyonlar
     * @param array<int, string> $adlar
     * @return list<array<string, mixed>>
     */
    private function restoranAdisyonPersonelOzeti(Collection $adisyonlar, string $personelAlani, array $adlar): array
    {
        return $adisyonlar
            ->filter(fn (RestoranAdisyonu $adisyon): bool => filled($adisyon->getAttribute($personelAlani)))
            ->groupBy($personelAlani)
            ->map(fn (Collection $satirlar, int $personelId): array => [
                'personel_id' => $personelId,
                'ad_soyad' => (string) ($adlar[$personelId] ?? '#'.$personelId),
                'adisyon_sayisi' => $satirlar->count(),
                'ciro' => round((float) $satirlar->sum('genel_toplam'), 2),
            ])
            ->sortByDesc('ciro')
            ->values()
            ->take(10)
            ->all();
    }

    /**
     * @return array{gorev_sayisi:int,tamamlanan_gorev_sayisi:int,personeller:list<array<string, mixed>>}
     */
    private function teknikServisPerformansi(int $firmaId, CarbonImmutable $bas, CarbonImmutable $son, ?int $subeId): array
    {
        $personeller = Personel::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereNotNull('kullanici_id')
            ->when($subeId, fn ($query) => $query->where('sube_id', $subeId))
            ->get(['id', 'kullanici_id', 'ad_soyad'])
            ->keyBy('kullanici_id');

        if ($personeller->isEmpty()) {
            return [
                'gorev_sayisi' => 0,
                'tamamlanan_gorev_sayisi' => 0,
                'personeller' => [],
            ];
        }

        $atamalar = TeknikServisGorevAtamasi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereIn('atanan_kullanici_id', $personeller->keys())
            ->where('baslangic_tarihi', '<=', $son)
            ->where(function ($query) use ($bas): void {
                $query->whereNull('bitis_tarihi')
                    ->orWhere('bitis_tarihi', '>=', $bas);
            })
            ->get(['id', 'atanan_kullanici_id', 'baslangic_tarihi', 'bitis_tarihi', 'durum']);

        return [
            'gorev_sayisi' => $atamalar->count(),
            'tamamlanan_gorev_sayisi' => $atamalar->whereNotNull('bitis_tarihi')->count(),
            'personeller' => $atamalar
                ->groupBy('atanan_kullanici_id')
                ->map(function (Collection $satirlar, int $kullaniciId) use ($personeller): array {
                    $personel = $personeller->get($kullaniciId);

                    return [
                        'personel_id' => (int) $personel?->id,
                        'ad_soyad' => (string) ($personel?->ad_soyad ?? '#'.$kullaniciId),
                        'gorev_sayisi' => $satirlar->count(),
                        'aktif_gorev_sayisi' => $satirlar->where('durum', 'aktif')->count(),
                        'tamamlanan_gorev_sayisi' => $satirlar->whereNotNull('bitis_tarihi')->count(),
                    ];
                })
                ->sortByDesc('gorev_sayisi')
                ->values()
                ->take(10)
                ->all(),
        ];
    }

    /**
     * @param iterable<int, int|string> $personelIds
     * @return array<int, string>
     */
    private function personelAdlari(int $firmaId, iterable $personelIds): array
    {
        $ids = collect($personelIds)
            ->map(fn (int|string $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return Personel::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereIn('id', $ids)
            ->pluck('ad_soyad', 'id')
            ->all();
    }
}
