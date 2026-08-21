<?php

namespace App\Services\Restoran;

use App\Models\Muhasebe\StokHareketi;
use App\Models\Restoran\RestoranAdisyonKalemi;
use App\Models\Restoran\RestoranAdisyonTahsilati;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Muhasebe\Enumlar\StokBelgeTuru;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class RestoranRaporServisi
{
    /**
     * @return array<string, float|int>
     */
    public function gunlukOzet(int $firmaId, Carbon|string $tarih): array
    {
        $baslangic = Carbon::parse($tarih)->startOfDay();
        $bitis = $baslangic->copy()->endOfDay();

        $satir = RestoranAdisyonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereBetween('acilis_at', [$baslangic, $bitis])
            ->selectRaw('COUNT(*) as adisyon_sayisi')
            ->selectRaw("SUM(CASE WHEN durum = ? THEN 1 ELSE 0 END) as kapali_adisyon_sayisi", [RestoranAdisyonu::DURUM_KAPANDI])
            ->selectRaw("SUM(CASE WHEN siparis_tipi = 'masa' THEN 1 ELSE 0 END) as masa_adisyon_sayisi")
            ->selectRaw("SUM(CASE WHEN siparis_tipi IN ('paket', 'online') THEN 1 ELSE 0 END) as paket_adisyon_sayisi")
            ->selectRaw('COALESCE(SUM(genel_toplam), 0) as toplam_tutar')
            ->selectRaw("COALESCE(SUM(CASE WHEN durum = ? THEN genel_toplam ELSE 0 END), 0) as tahsil_edilen_tutar", [RestoranAdisyonu::DURUM_KAPANDI])
            ->first();

        return [
            'adisyon_sayisi' => (int) ($satir?->adisyon_sayisi ?? 0),
            'kapali_adisyon_sayisi' => (int) ($satir?->kapali_adisyon_sayisi ?? 0),
            'masa_adisyon_sayisi' => (int) ($satir?->masa_adisyon_sayisi ?? 0),
            'paket_adisyon_sayisi' => (int) ($satir?->paket_adisyon_sayisi ?? 0),
            'toplam_tutar' => round((float) ($satir?->toplam_tutar ?? 0), 2),
            'tahsil_edilen_tutar' => round((float) ($satir?->tahsil_edilen_tutar ?? 0), 2),
        ];
    }

    /**
     * @return Collection<int, object>
     */
    public function garsonPerformansi(int $firmaId, Carbon|string $baslangic, Carbon|string $bitis): Collection
    {
        return RestoranAdisyonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereNotNull('garson_personel_id')
            ->whereBetween('acilis_at', [Carbon::parse($baslangic)->startOfDay(), Carbon::parse($bitis)->endOfDay()])
            ->groupBy('garson_personel_id')
            ->select('garson_personel_id')
            ->selectRaw('COUNT(*) as adisyon_sayisi')
            ->selectRaw('COALESCE(SUM(genel_toplam), 0) as toplam_tutar')
            ->orderByDesc('toplam_tutar')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function kuryePerformansi(int $firmaId, Carbon|string $baslangic, Carbon|string $bitis): Collection
    {
        return RestoranAdisyonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereNotNull('kurye_personel_id')
            ->whereBetween('teslimat_at', [Carbon::parse($baslangic)->startOfDay(), Carbon::parse($bitis)->endOfDay()])
            ->where('paket_durum', RestoranAdisyonu::PAKET_DURUM_TESLIM_EDILDI)
            ->groupBy('kurye_personel_id')
            ->select('kurye_personel_id')
            ->selectRaw('COUNT(*) as teslimat_sayisi')
            ->selectRaw('COALESCE(SUM(genel_toplam), 0) as teslimat_tutari')
            ->orderByDesc('teslimat_sayisi')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function kasiyerPerformansi(int $firmaId, Carbon|string $baslangic, Carbon|string $bitis): Collection
    {
        return RestoranAdisyonTahsilati::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->join('restoran_adisyonlari as adisyonlar', function ($join) use ($firmaId): void {
                $join
                    ->on('adisyonlar.id', '=', 'restoran_adisyon_tahsilatlari.adisyon_id')
                    ->where('adisyonlar.firma_id', '=', $firmaId);
            })
            ->where('restoran_adisyon_tahsilatlari.firma_id', $firmaId)
            ->where('restoran_adisyon_tahsilatlari.durum', RestoranAdisyonTahsilati::DURUM_AKTIF)
            ->whereNotNull('adisyonlar.kasiyer_personel_id')
            ->whereBetween('restoran_adisyon_tahsilatlari.tahsilat_at', [Carbon::parse($baslangic)->startOfDay(), Carbon::parse($bitis)->endOfDay()])
            ->groupBy('adisyonlar.kasiyer_personel_id')
            ->selectRaw('adisyonlar.kasiyer_personel_id as kasiyer_personel_id')
            ->selectRaw('COUNT(*) as tahsilat_sayisi')
            ->selectRaw('COUNT(DISTINCT restoran_adisyon_tahsilatlari.adisyon_id) as adisyon_sayisi')
            ->selectRaw('COALESCE(SUM(restoran_adisyon_tahsilatlari.tutar), 0) as tahsilat_tutari')
            ->orderByDesc('tahsilat_tutari')
            ->get();
    }

    /**
     * @return array<string, float|int>
     */
    public function paketOperasyonOzeti(int $firmaId, Carbon|string $baslangic, Carbon|string $bitis): array
    {
        $baslangic = Carbon::parse($baslangic)->startOfDay();
        $bitis = Carbon::parse($bitis)->endOfDay();

        $satir = RestoranAdisyonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereIn('siparis_tipi', ['paket', 'online'])
            ->whereBetween('acilis_at', [$baslangic, $bitis])
            ->selectRaw('COUNT(*) as siparis_sayisi')
            ->selectRaw("SUM(CASE WHEN paket_durum = ? THEN 1 ELSE 0 END) as hazirlaniyor_sayisi", [RestoranAdisyonu::PAKET_DURUM_HAZIRLANIYOR])
            ->selectRaw("SUM(CASE WHEN paket_durum = ? THEN 1 ELSE 0 END) as kuryede_sayisi", [RestoranAdisyonu::PAKET_DURUM_KURYEE_ATANDI])
            ->selectRaw("SUM(CASE WHEN paket_durum = ? THEN 1 ELSE 0 END) as yolda_sayisi", [RestoranAdisyonu::PAKET_DURUM_YOLDA])
            ->selectRaw("SUM(CASE WHEN paket_durum = ? THEN 1 ELSE 0 END) as teslim_edildi_sayisi", [RestoranAdisyonu::PAKET_DURUM_TESLIM_EDILDI])
            ->selectRaw("SUM(CASE WHEN paket_durum = ? THEN 1 ELSE 0 END) as iptal_sayisi", [RestoranAdisyonu::PAKET_DURUM_IPTAL])
            ->selectRaw('COALESCE(SUM(genel_toplam), 0) as toplam_tutar')
            ->first();

        $gecikenSayisi = RestoranAdisyonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereIn('siparis_tipi', ['paket', 'online'])
            ->whereBetween('acilis_at', [$baslangic, $bitis])
            ->whereNotNull('tahmini_teslimat_at')
            ->where(function ($query): void {
                $query
                    ->whereNull('teslimat_at')
                    ->where('tahmini_teslimat_at', '<', now())
                    ->orWhereColumn('teslimat_at', '>', 'tahmini_teslimat_at');
            })
            ->count();

        $teslimSureleri = RestoranAdisyonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereIn('siparis_tipi', ['paket', 'online'])
            ->whereBetween('acilis_at', [$baslangic, $bitis])
            ->whereNotNull('teslimat_at')
            ->get(['acilis_at', 'teslimat_at'])
            ->map(fn (RestoranAdisyonu $adisyon): int => Carbon::parse($adisyon->acilis_at)->diffInMinutes(Carbon::parse($adisyon->teslimat_at)));

        return [
            'siparis_sayisi' => (int) ($satir?->siparis_sayisi ?? 0),
            'hazirlaniyor_sayisi' => (int) ($satir?->hazirlaniyor_sayisi ?? 0),
            'kuryede_sayisi' => (int) ($satir?->kuryede_sayisi ?? 0),
            'yolda_sayisi' => (int) ($satir?->yolda_sayisi ?? 0),
            'teslim_edildi_sayisi' => (int) ($satir?->teslim_edildi_sayisi ?? 0),
            'iptal_sayisi' => (int) ($satir?->iptal_sayisi ?? 0),
            'geciken_sayisi' => (int) $gecikenSayisi,
            'ortalama_teslimat_dakika' => $teslimSureleri->isEmpty() ? 0 : round((float) $teslimSureleri->avg(), 2),
            'toplam_tutar' => round((float) ($satir?->toplam_tutar ?? 0), 2),
        ];
    }

    /**
     * @return Collection<int, object>
     */
    public function mutfakPerformansi(int $firmaId, Carbon|string $baslangic, Carbon|string $bitis): Collection
    {
        return RestoranAdisyonKalemi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereNotNull('hazirlayan_personel_id')
            ->whereBetween('updated_at', [Carbon::parse($baslangic)->startOfDay(), Carbon::parse($bitis)->endOfDay()])
            ->whereIn('durum', [
                RestoranAdisyonKalemi::DURUM_HAZIR,
                RestoranAdisyonKalemi::DURUM_SERVIS_EDILDI,
            ])
            ->groupBy('hazirlayan_personel_id')
            ->select('hazirlayan_personel_id')
            ->selectRaw('COUNT(*) as kalem_sayisi')
            ->selectRaw('COALESCE(SUM(toplam_tutar), 0) as toplam_tutar')
            ->orderByDesc('kalem_sayisi')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function tahsilatKanalOzeti(int $firmaId, Carbon|string $baslangic, Carbon|string $bitis): Collection
    {
        return RestoranAdisyonTahsilati::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->where('durum', RestoranAdisyonTahsilati::DURUM_AKTIF)
            ->whereBetween('tahsilat_at', [Carbon::parse($baslangic)->startOfDay(), Carbon::parse($bitis)->endOfDay()])
            ->groupBy('odeme_kanali', 'para_birimi')
            ->select('odeme_kanali', 'para_birimi')
            ->selectRaw('COUNT(*) as tahsilat_sayisi')
            ->selectRaw('COALESCE(SUM(tutar), 0) as toplam_tutar')
            ->orderByDesc('toplam_tutar')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function urunSatisOzeti(int $firmaId, Carbon|string $baslangic, Carbon|string $bitis, int $limit = 10): Collection
    {
        $baslangic = Carbon::parse($baslangic)->startOfDay();
        $bitis = Carbon::parse($bitis)->endOfDay();

        return RestoranAdisyonKalemi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->where('durum', '!=', RestoranAdisyonKalemi::DURUM_IPTAL)
            ->whereHas('adisyon', function ($query) use ($firmaId, $baslangic, $bitis): void {
                $query
                    ->withoutGlobalScope(FirmaIdTenantScope::class)
                    ->where('firma_id', $firmaId)
                    ->where('durum', '!=', RestoranAdisyonu::DURUM_IPTAL)
                    ->whereBetween('acilis_at', [$baslangic, $bitis]);
            })
            ->groupBy('menu_urunu_id', 'urun_adi')
            ->select('menu_urunu_id', 'urun_adi')
            ->selectRaw('COALESCE(SUM(miktar), 0) as toplam_miktar')
            ->selectRaw('COALESCE(SUM(toplam_tutar), 0) as toplam_tutar')
            ->selectRaw('COALESCE(SUM(ikram_tutari), 0) as ikram_tutari')
            ->orderByDesc('toplam_tutar')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<string, float>
     */
    public function stokKarlilikOzeti(int $firmaId, Carbon|string $baslangic, Carbon|string $bitis): array
    {
        $baslangic = Carbon::parse($baslangic)->startOfDay();
        $bitis = Carbon::parse($bitis)->endOfDay();

        $satisTutari = RestoranAdisyonKalemi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->where('durum', '!=', RestoranAdisyonKalemi::DURUM_IPTAL)
            ->whereHas('adisyon', function ($query) use ($firmaId, $baslangic, $bitis): void {
                $query
                    ->withoutGlobalScope(FirmaIdTenantScope::class)
                    ->where('firma_id', $firmaId)
                    ->where('durum', RestoranAdisyonu::DURUM_KAPANDI)
                    ->whereBetween('tahsilat_at', [$baslangic, $bitis]);
            })
            ->sum('toplam_tutar');

        $stokMaliyeti = StokHareketi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->where('belge_turu', StokBelgeTuru::RestoranAdisyon->value)
            ->whereBetween('tarih', [$baslangic, $bitis])
            ->sum('toplam_maliyet');

        $satisTutari = round((float) $satisTutari, 2);
        $stokMaliyeti = round((float) $stokMaliyeti, 2);

        return [
            'satis_tutari' => $satisTutari,
            'stok_maliyeti' => $stokMaliyeti,
            'brut_kar' => round($satisTutari - $stokMaliyeti, 2),
            'brut_kar_orani' => $satisTutari > 0 ? round((($satisTutari - $stokMaliyeti) / $satisTutari) * 100, 2) : 0.0,
        ];
    }
}
