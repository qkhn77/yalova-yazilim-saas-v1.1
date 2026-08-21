<?php

namespace App\Filament\Clusters\Muhasebe\Widgets;

use App\Models\Muhasebe\AlacakPlanTaksiti;
use App\Services\TenantContextService;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class VadeTakipOzetWidget extends BaseWidget
{
    protected static bool $isLazy = true;

    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        if ($firmaId < 1) {
            return [
                Stat::make('Acik alacak', '0,00 TRY')->color('gray'),
                Stat::make('Geciken', '0,00 TRY')->color('gray'),
                Stat::make('Bugun', '0,00 TRY')->color('gray'),
                Stat::make('7 gun', '0,00 TRY')->color('gray'),
            ];
        }

        $bugun = Carbon::today()->toDateString();
        $yediGunSonra = Carbon::today()->addDays(7)->toDateString();

        $satirlar = Cache::remember(
            'muhasebe:vade-widget-ozet:v3:'.$firmaId.':'.$bugun,
            now()->addMinutes(5),
            fn (): Collection => $this->acikTaksitSorgusu($firmaId)
                ->selectRaw('plan.para_birimi as para_birimi')
                ->selectRaw('COALESCE(SUM(muhasebe_alacak_plan_taksitleri.kalan_tutar), 0) as acik_toplam')
                ->selectRaw('COUNT(muhasebe_alacak_plan_taksitleri.id) as acik_adet')
                ->selectRaw('COALESCE(SUM(CASE WHEN muhasebe_alacak_plan_taksitleri.vade_tarihi < ? THEN muhasebe_alacak_plan_taksitleri.kalan_tutar ELSE 0 END), 0) as geciken_toplam', [$bugun])
                ->selectRaw('SUM(CASE WHEN muhasebe_alacak_plan_taksitleri.vade_tarihi < ? THEN 1 ELSE 0 END) as geciken_adet', [$bugun])
                ->selectRaw('COALESCE(SUM(CASE WHEN muhasebe_alacak_plan_taksitleri.vade_tarihi = ? THEN muhasebe_alacak_plan_taksitleri.kalan_tutar ELSE 0 END), 0) as bugun_toplam', [$bugun])
                ->selectRaw('SUM(CASE WHEN muhasebe_alacak_plan_taksitleri.vade_tarihi = ? THEN 1 ELSE 0 END) as bugun_adet', [$bugun])
                ->selectRaw('COALESCE(SUM(CASE WHEN muhasebe_alacak_plan_taksitleri.vade_tarihi BETWEEN ? AND ? THEN muhasebe_alacak_plan_taksitleri.kalan_tutar ELSE 0 END), 0) as yedi_gun_toplam', [$bugun, $yediGunSonra])
                ->selectRaw('SUM(CASE WHEN muhasebe_alacak_plan_taksitleri.vade_tarihi BETWEEN ? AND ? THEN 1 ELSE 0 END) as yedi_gun_adet', [$bugun, $yediGunSonra])
                ->groupBy('plan.para_birimi')
                ->orderBy('plan.para_birimi')
                ->get()
        );

        return [
            Stat::make('Acik alacak', $this->toplamMetni($satirlar, 'acik_toplam'))
                ->description($this->adetMetni($satirlar, 'acik_adet'))
                ->color('info'),
            Stat::make('Geciken', $this->toplamMetni($satirlar, 'geciken_toplam'))
                ->description($this->adetMetni($satirlar, 'geciken_adet'))
                ->color('danger'),
            Stat::make('Bugun', $this->toplamMetni($satirlar, 'bugun_toplam'))
                ->description($this->adetMetni($satirlar, 'bugun_adet'))
                ->color('warning'),
            Stat::make('7 gun', $this->toplamMetni($satirlar, 'yedi_gun_toplam'))
                ->description($this->adetMetni($satirlar, 'yedi_gun_adet'))
                ->color('success'),
        ];
    }

    private function acikTaksitSorgusu(int $firmaId): Builder
    {
        return AlacakPlanTaksiti::query()
            ->join('muhasebe_alacak_planlari as plan', 'plan.id', '=', 'muhasebe_alacak_plan_taksitleri.alacak_plan_id')
            ->where('muhasebe_alacak_plan_taksitleri.firma_id', $firmaId)
            ->where('plan.firma_id', $firmaId)
            ->whereNull('plan.deleted_at')
            ->where('muhasebe_alacak_plan_taksitleri.kalan_tutar', '>', 0)
            ->whereNotIn('muhasebe_alacak_plan_taksitleri.durum', ['odendi', 'iptal']);
    }

    /**
     * @param  Collection<int, object>  $satirlar
     */
    private function toplamMetni(Collection $satirlar, string $alan): string
    {
        if ($satirlar->isEmpty()) {
            return '0,00 TRY';
        }

        $metin = $satirlar
            ->filter(fn (object $satir): bool => (float) ($satir->{$alan} ?? 0) > 0)
            ->map(fn (object $satir): string => number_format((float) ($satir->{$alan} ?? 0), 2, ',', '.').' '.strtoupper((string) ($satir->para_birimi ?: 'TRY')))
            ->implode(' / ');

        return $metin !== '' ? $metin : '0,00 TRY';
    }

    /**
     * @param  Collection<int, object>  $satirlar
     */
    private function adetMetni(Collection $satirlar, string $alan): string
    {
        $adet = (int) $satirlar->sum(fn (object $satir): int => (int) ($satir->{$alan} ?? 0));

        return $adet.' vade';
    }
}
