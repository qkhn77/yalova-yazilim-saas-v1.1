<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Filament\Clusters\Muhasebe\Resources\BankaHesabiKaynagi;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Pages\Page;

class Bankalar extends Page
{
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = Muhasebe::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Bankalar';

    protected static ?string $slug = 'finans/bankalar';

    protected static string $view = 'filament.clusters.muhasebe.pages.bankalar';

    public function mount(): void
    {
        $this->redirect(BankaHesabiKaynagi::getUrl());
    }

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::FINANS_GORUNTULE;
    }
}
