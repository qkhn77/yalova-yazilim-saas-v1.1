<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DenetimKayidiKaynagi;
use App\Filament\Resources\FirmaYonetimKaynagi;
use App\Filament\Resources\PlanYonetimKaynagi;
use App\Models\DenetimKayidi;
use App\Models\Firma;
use App\Models\Plan;
use App\Support\SaaSemaYardimcisi;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SistemYonetimiOzetWidget extends BaseWidget
{
    protected static ?int $sort = -20;

    // Yerel PHP sunucusunda uzun süren sorgular üst üste binerek paneli
    // kilitlemesin. Veriler zaten 60 saniye önbelleğe alınıyor ve sayfa
    // yenilendiğinde güncel değerler gösteriliyor.
    protected static ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $kullanici = Auth::user();
        if (! $kullanici) {
            return false;
        }

        return (bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false);
    }

    protected function getStats(): array
    {
        return Cache::remember(
            'filament:sistem-yonetimi-ozet-widget:v1',
            now()->addSeconds(60),
            fn (): array => $this->statsOlustur(),
        );
    }

    protected function statsOlustur(): array
    {
        $istatistikler = [];

        if (SaaSemaYardimcisi::firmalarTablosuVarMi()) {
            try {
                $toplamFirma = Firma::query()->count();
                $beklemede = Firma::query()->where('durum', Firma::DURUM_BEKLEMEDE)->count();
                $istatistikler[] = Stat::make('Firmalar', (string) $toplamFirma)
                    ->description('Kayıtlı firma')
                    ->descriptionIcon('heroicon-o-building-office-2')
                    ->color('primary')
                    ->url(FirmaYonetimKaynagi::getUrl());
                $istatistikler[] = Stat::make('Beklemede', (string) $beklemede)
                    ->description('Onay bekleyen firma')
                    ->descriptionIcon('heroicon-o-clock')
                    ->color($beklemede > 0 ? 'warning' : 'gray')
                    ->url(FirmaYonetimKaynagi::getUrl());
            } catch (\Throwable) {
                // sessiz
            }
        }

        if (SaaSemaYardimcisi::planlarTablosuVarMi()) {
            try {
                $planSayisi = Plan::query()->count();
                $istatistikler[] = Stat::make('Planlar', (string) $planSayisi)
                    ->description('Tanımlı plan')
                    ->descriptionIcon('heroicon-o-rectangle-stack')
                    ->color('success')
                    ->url(PlanYonetimKaynagi::getUrl());
            } catch (\Throwable) {
                // sessiz
            }
        }

        if (SaaSemaYardimcisi::tabloVarMi('denetim_kayitlari')) {
            try {
                $son24 = DenetimKayidi::query()
                    ->where('created_at', '>=', now()->subDay())
                    ->count();
                $denetimStat = Stat::make('Denetim (24s)', (string) $son24)
                    ->description('Son 24 saatteki kayıt')
                    ->descriptionIcon('heroicon-o-clipboard-document-list')
                    ->color('info');
                $istatistikler[] = $denetimStat->url(DenetimKayidiKaynagi::getUrl());
            } catch (\Throwable) {
                // sessiz
            }
        }

        if ($istatistikler === []) {
            $istatistikler[] = Stat::make('Sistem', '—')
                ->description('Özet için firma / plan tabloları gerekli')
                ->color('gray');
        }

        return array_map(
            static fn (Stat $stat): Stat => $stat->extraAttributes(['class' => 'yk-info-card']),
            $istatistikler,
        );
    }
}
