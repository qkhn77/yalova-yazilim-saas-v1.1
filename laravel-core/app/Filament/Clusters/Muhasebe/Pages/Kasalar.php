<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Filament\Clusters\Muhasebe\Resources\KasaHesabiKaynagi;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Pages\Page;

class Kasalar extends Page
{
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = Muhasebe::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Kasalar';

    protected static ?string $slug = 'finans/kasalar';

    protected static string $view = 'filament.clusters.muhasebe.pages.kasalar';

    public function mount(): void
    {
        $this->redirect(KasaHesabiKaynagi::getUrl());
    }

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::FINANS_GORUNTULE;
    }
}
