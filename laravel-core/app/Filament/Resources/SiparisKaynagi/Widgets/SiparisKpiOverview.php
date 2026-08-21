<?php

namespace App\Filament\Resources\SiparisKaynagi\Widgets;

use App\Models\Ecommerce\Odeme;
use App\Models\Ecommerce\Siparis;
use App\Services\TenantContextService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SiparisKpiOverview extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected function temelSorgu(): Builder
    {
        $q = Siparis::query();
        $fid = app(TenantContextService::class)->aktifFirmaId();
        if ($fid) {
            $q->where('firma_id', $fid);
        }

        return $q;
    }

    protected function getStats(): array
    {
        $q = $this->temelSorgu();
        $today = now();
        $start30 = $today->copy()->subDays(29)->startOfDay();

        $period = (clone $q)->whereBetween('created_at', [$start30, $today->copy()->endOfDay()]);
        $toplamSiparis = (clone $period)->count();

        $gmvMap = $this->pbToplamlari(
            (clone $period)->whereIn('durum', $this->gmvDurumlari())
        );

        $iptalMap = $this->pbToplamlari(
            (clone $period)->whereIn('durum', [Siparis::DURUM_IPTAL_EDILDI, Siparis::DURUM_IPTAL])
        );

        $iadeMap = $this->pbToplamlari(
            (clone $period)->whereIn('durum', [Siparis::DURUM_IADE_EDILDI])
        );

        $netMap = $this->mapCikar($this->mapCikar($gmvMap, $iptalMap), $iadeMap);

        $gmvSiparisAdedi = (clone $period)->whereIn('durum', $this->gmvDurumlari())->count();
        $aovMap = $this->mapBol($gmvMap, max(1, $gmvSiparisAdedi));

        $bekleyenSiparis = (clone $period)
            ->whereIn('durum', [Siparis::DURUM_ONAY_BEKLIYOR, Siparis::DURUM_IPTAL_TALEBI, Siparis::DURUM_IADE_TALEBI, Siparis::DURUM_ODEME_BEKLENIYOR])
            ->count();

        $iptalAdedi = (clone $period)->whereIn('durum', [Siparis::DURUM_IPTAL_EDILDI, Siparis::DURUM_IPTAL])->count();
        $iadeAdedi = (clone $period)->whereIn('durum', [Siparis::DURUM_IADE_EDILDI])->count();

        $iptalOrani = $toplamSiparis > 0 ? round(($iptalAdedi / $toplamSiparis) * 100, 2) : 0.0;
        $iadeOrani = $toplamSiparis > 0 ? round(($iadeAdedi / $toplamSiparis) * 100, 2) : 0.0;

        $odemeOran = $this->basarisizOdemeOrani($start30, $today->copy()->endOfDay());

        return [
            Stat::make('Toplam Ciro (GMV)', $this->pbMapYaz($gmvMap))
                ->description('Son 30 gun')
                ->color('success'),
            Stat::make('Net Ciro', $this->pbMapYaz($netMap))
                ->description('Iptal + iade dusulmus (son 30 gun)')
                ->color('primary'),
            Stat::make('Siparis Sayisi', (string) $toplamSiparis)
                ->description('Son 30 gun toplam siparis')
                ->color('info'),
            Stat::make('AOV', $this->pbMapYaz($aovMap))
                ->description('Ortalama sepet tutari (son 30 gun)')
                ->color('gray'),
            Stat::make('Basarisiz Odeme Orani', number_format($odemeOran, 2, ',', '.').'%')
                ->description('Son 30 gun odeme denemeleri')
                ->color($odemeOran >= 15 ? 'danger' : 'warning'),
            Stat::make('Iptal Orani', number_format($iptalOrani, 2, ',', '.').'%')
                ->description('Son 30 gun')
                ->color($iptalOrani >= 10 ? 'danger' : 'warning'),
            Stat::make('Iade Orani', number_format($iadeOrani, 2, ',', '.').'%')
                ->description('Son 30 gun')
                ->color($iadeOrani >= 10 ? 'danger' : 'warning'),
            Stat::make('Bekleyen Siparis', (string) $bekleyenSiparis)
                ->description('Onay bekliyor + iptal/iade talebi')
                ->color('warning'),
        ];
    }

    /**
     * @return list<string>
     */
    private function gmvDurumlari(): array
    {
        return [
            Siparis::DURUM_ONAYLANDI_YENI,
            Siparis::DURUM_GONDERILDI,
            Siparis::DURUM_TESLIM_EDILDI,
            Siparis::DURUM_IPTAL_TALEBI,
            Siparis::DURUM_IPTAL_EDILDI,
            Siparis::DURUM_IADE_TALEBI,
            Siparis::DURUM_IADE_EDILDI,
            Siparis::DURUM_ODENDI,
            Siparis::DURUM_HAZIRLANIYOR,
            Siparis::DURUM_KARGOLANDI,
            Siparis::DURUM_TAMAMLANDI,
            Siparis::DURUM_BEKLEMEDE,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function pbToplamlari(Builder $query): array
    {
        $rows = $query
            ->select([
                DB::raw("COALESCE(NULLIF(para_birimi, ''), 'TRY') as para_birimi"),
                DB::raw('SUM(genel_toplam) as toplam'),
            ])
            ->groupBy('para_birimi')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $pb = strtoupper((string) ($row->para_birimi ?: 'TRY'));
            $map[$pb] = (float) $row->toplam;
        }

        ksort($map);

        return $map;
    }

    /**
     * @param  array<string, float>  $left
     * @param  array<string, float>  $right
     * @return array<string, float>
     */
    private function mapCikar(array $left, array $right): array
    {
        $all = array_unique(array_merge(array_keys($left), array_keys($right)));
        $out = [];
        foreach ($all as $pb) {
            $out[$pb] = round(((float) ($left[$pb] ?? 0)) - ((float) ($right[$pb] ?? 0)), 2);
        }

        ksort($out);

        return $out;
    }

    /**
     * @param  array<string, float>  $map
     * @return array<string, float>
     */
    private function mapBol(array $map, int $sayi): array
    {
        if ($sayi <= 0) {
            return $map;
        }

        $out = [];
        foreach ($map as $pb => $tutar) {
            $out[$pb] = round($tutar / $sayi, 2);
        }

        return $out;
    }

    /**
     * @param  array<string, float>  $map
     */
    private function pbMapYaz(array $map): string
    {
        if ($map === []) {
            return '0,00 TRY';
        }

        $parcalar = [];
        foreach ($map as $pb => $tutar) {
            $parcalar[] = number_format((float) $tutar, 2, ',', '.').' '.$pb;
        }

        return implode(' | ', $parcalar);
    }

    private function basarisizOdemeOrani($start, $end): float
    {
        $odeme = Odeme::query()->whereBetween('created_at', [$start, $end]);

        $fid = app(TenantContextService::class)->aktifFirmaId();
        if ($fid) {
            $odeme->whereHas('siparis', fn (Builder $q) => $q->where('firma_id', $fid));
        }

        $toplam = (clone $odeme)->count();
        if ($toplam <= 0) {
            return 0.0;
        }

        $basarisiz = (clone $odeme)->where('durum', Odeme::DURUM_BASARISIZ)->count();

        return round(($basarisiz / $toplam) * 100, 2);
    }
}