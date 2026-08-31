<?php

namespace App\Filament\Clusters\Muhasebe\Concerns;

use App\Filament\Clusters\Muhasebe\Pages\FaturaListesiFiltreliSayfasi;
use App\Services\TenantContextService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Fatura filtreli liste sayfalarında tablo ile aynı filtre/arama kapsamında KPI üretir.
 *
 * @mixin FaturaListesiFiltreliSayfasi
 */
trait FaturaListeKpiHesaplari
{
    /**
     * @return array<int, array{
     *     label: string,
     *     value: string,
     *     description: string,
     *     color: 'primary'|'success'|'warning'|'danger',
     *     icon: string
     * }>
     */
    public function faturaListeKpiKartlari(): array
    {
        $satirlar = $this->faturaListeKpiHamSatir();

        $kayit = (int) $satirlar->sum('kayit_sayisi');
        $toplamGenel = $this->kpiParaDagilimi($satirlar, 'toplam_genel');
        $toplamAcik = $this->kpiParaDagilimi($satirlar, 'toplam_acik');
        $vadesiGecmis = $this->kpiParaDagilimi($satirlar, 'vadesi_gecmis_acik');
        $buAy = $this->kpiParaDagilimi($satirlar, 'bu_ay_genel');

        $acikPozitif = (float) $satirlar->sum('toplam_acik') > 0;
        $vadePozitif = (float) $satirlar->sum('vadesi_gecmis_acik') > 0;

        $kartlar = [
            [
                'label' => 'Kayıt sayısı',
                'value' => number_format($kayit, 0, ',', '.'),
                'description' => 'Filtreye uyan toplam fatura adedi',
                'color' => 'primary',
                'icon' => 'heroicon-m-document-text',
            ],
            [
                'label' => 'Genel toplam',
                'value' => $toplamGenel,
                'description' => 'Filtrelenen kayıtların toplam tutarı',
                'color' => 'success',
                'icon' => 'heroicon-m-banknotes',
            ],
            [
                'label' => 'Açık tutar',
                'value' => $toplamAcik,
                'description' => 'Henüz kapanmamış bakiye toplamı',
                'color' => $acikPozitif ? 'warning' : 'success',
                'icon' => 'heroicon-m-clock',
            ],
            [
                'label' => 'Vadesi geçmiş açık',
                'value' => $vadesiGecmis,
                'description' => 'Bugün itibarıyla vadesi geçen bakiye',
                'color' => $vadePozitif ? 'danger' : 'success',
                'icon' => 'heroicon-m-exclamation-triangle',
            ],
        ];

        if (static::faturaListesindeBuAyKpiGoster()) {
            $kartlar[] = [
                'label' => 'Bu ay toplam',
                'value' => $buAy,
                'description' => 'Bu ay tarihli kayıtların toplamı',
                'color' => 'primary',
                'icon' => 'heroicon-m-calendar-days',
            ];
        }

        return $kartlar;
    }

    /**
     * İstenirse alt sınıfta kapatılır (ör. çok dar filtreli sayfalar).
     */
    public static function faturaListesindeBuAyKpiGoster(): bool
    {
        return true;
    }

    public static function listeIslemEtiketi(): string
    {
        return (string) (static::$title ?? 'Faturalar');
    }

    protected function faturaListeKpiHamSatir(): Collection
    {
        $sub = $this->getFilteredTableQuery()->clone()
            ->reorder()
            ->select([
                'faturalar.id',
                'faturalar.tarih',
                'faturalar.vade_tarihi',
                'faturalar.genel_toplam',
                'faturalar.acik_tutar',
                'faturalar.para_birimi',
            ]);
        $driver = $sub->getModel()->getConnection()->getDriverName();
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $nextMonthStart = now()->addMonthNoOverflow()->startOfMonth()->toDateString();
        $firmaId = app(TenantContextService::class)->aktifFirmaId();

        $cacheKey = 'muhasebe:fatura-liste:kpi:firma:'.($firmaId ?? 'yok').':'.md5(static::class.'|'.$driver.'|'.$sub->toSql().'|'.json_encode($sub->getBindings(), JSON_UNESCAPED_UNICODE));

        return Cache::remember($cacheKey, now()->addSeconds(30), function () use ($sub, $driver, $today, $monthStart, $nextMonthStart): object {
            if ($driver === 'sqlite') {
                return DB::query()->fromSub($sub, 'f')
                    ->selectRaw("UPPER(COALESCE(f.para_birimi, 'TRY')) as para_birimi")
                    ->selectRaw('COUNT(*) as kayit_sayisi')
                    ->selectRaw('COALESCE(SUM(f.genel_toplam), 0) as toplam_genel')
                    ->selectRaw('COALESCE(SUM(f.acik_tutar), 0) as toplam_acik')
                    ->selectRaw(
                        'COALESCE(SUM(CASE WHEN f.vade_tarihi IS NOT NULL AND f.vade_tarihi < ? AND CAST(f.acik_tutar AS REAL) > 0 THEN f.acik_tutar ELSE 0 END), 0) as vadesi_gecmis_acik',
                        [$today]
                    )
                    ->selectRaw(
                        'COALESCE(SUM(CASE WHEN f.tarih >= ? AND f.tarih < ? THEN f.genel_toplam ELSE 0 END), 0) as bu_ay_genel',
                        [$monthStart, $nextMonthStart]
                    )
                    ->groupBy('f.para_birimi')
                    ->get();
            }

            return DB::query()->fromSub($sub, 'f')
                ->selectRaw("UPPER(COALESCE(f.para_birimi, 'TRY')) as para_birimi")
                ->selectRaw('COUNT(*) as kayit_sayisi')
                ->selectRaw('COALESCE(SUM(f.genel_toplam), 0) as toplam_genel')
                ->selectRaw('COALESCE(SUM(f.acik_tutar), 0) as toplam_acik')
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN f.vade_tarihi IS NOT NULL AND f.vade_tarihi < ? AND f.acik_tutar > 0 THEN f.acik_tutar ELSE 0 END), 0) as vadesi_gecmis_acik',
                    [$today]
                )
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN f.tarih >= ? AND f.tarih < ? THEN f.genel_toplam ELSE 0 END), 0) as bu_ay_genel',
                    [$monthStart, $nextMonthStart]
                )
                ->groupBy('f.para_birimi')
                ->get();
        });
    }

    protected function kpiParaDagilimi(Collection $satirlar, string $alan): string
    {
        return $satirlar
            ->filter(fn (object $satir): bool => abs((float) ($satir->{$alan} ?? 0)) > 0.000001)
            ->map(fn (object $satir): string => $this->formatPara((string) ($satir->{$alan} ?? 0), (string) ($satir->para_birimi ?? 'TRY')))
            ->implode(' · ') ?: $this->formatPara('0', 'TRY');
    }

    protected function formatPara(string $tutar, string $paraBirimi = 'TRY'): string
    {
        $normalized = bcadd(is_numeric($tutar) ? $tutar : '0', '0', 2);
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '00');
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole) ?: '0';

        return ($negative ? '-' : '').$whole.','.$fraction.' '.strtoupper($paraBirimi ?: 'TRY');
    }
}
