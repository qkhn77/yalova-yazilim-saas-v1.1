<?php

namespace App\Filament\Clusters\Restoran\Pages;

use App\Filament\Clusters\Restoran as RestoranCluster;
use App\Filament\Clusters\Restoran\Kaynaklar\RestoranFilamentErisimYardimcisi;
use App\Services\Restoran\RestoranGunSonuMutabakatServisi;
use App\Services\TenantContextService;
use App\Support\Restoran\RestoranYetkiSablonlari;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class RestoranGunSonuMutabakatSayfasi extends Page
{
    protected static ?string $cluster = RestoranCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Gün sonu';

    protected static ?string $title = 'Restoran Gün Sonu';

    protected static ?string $slug = 'raporlar/gun-sonu';

    protected static string $view = 'filament.clusters.restoran.pages.restoran-gun-sonu-mutabakat';

    public string $tarih;

    public ?string $farkAciklamasi = null;

    public ?string $notlar = null;

    public function mount(): void
    {
        $this->tarih = now()->toDateString();
    }

    public static function canAccess(): bool
    {
        return RestoranFilamentErisimYardimcisi::restoranYetkisiVarMi(RestoranYetkiSablonlari::GUN_SONU_GORUNTULE);
    }

    /**
     * @return array<string, mixed>
     */
    public function mutabakat(): array
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        if (! $firmaId) {
            return [
                'tarih' => $this->tarih,
                'toplam_tahsilat' => 0,
                'toplam_muhasebe' => 0,
                'toplam_fark' => 0,
                'mutabik_mi' => true,
                'kanallar' => [],
            ];
        }

        return app(RestoranGunSonuMutabakatServisi::class)->gunlukOzet((int) $firmaId, $this->tarih);
    }

    public function gunSonunuKapat(): void
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        if (! $firmaId) {
            return;
        }

        $kapanis = app(RestoranGunSonuMutabakatServisi::class)->kapanisKaydet(
            (int) $firmaId,
            $this->tarih,
            $this->farkAciklamasi,
            $this->notlar,
            auth()->id() ? (int) auth()->id() : null
        );

        $this->farkAciklamasi = $kapanis->fark_aciklamasi;
        $this->notlar = $kapanis->notlar;

        Notification::make()
            ->title('Gün sonu kapanışı kaydedildi')
            ->success()
            ->send();
    }
}
