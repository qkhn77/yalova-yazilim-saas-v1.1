<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Pages\Page;

/**
 * Eski "Finans fişleri" yolu; liste ekranına yönlendirir.
 */
class FinansFisKaynagiSayfasi extends Page
{
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Finans Fişleri';

    protected static ?string $slug = 'finans/finans-fisleri';

    protected static string $view = 'filament.clusters.muhasebe.pages.finans-fis-yonlendirme';

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::FINANS_GORUNTULE;
    }

    public function mount(): void
    {
        $this->redirect(FinansHareketleriListesiSayfasi::getUrl());
    }
}
