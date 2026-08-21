<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Pages\Page;

class Cari extends Page
{
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = Muhasebe::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Cari';

    protected static ?string $slug = 'cari';

    protected static string $view = 'filament.clusters.muhasebe.pages.cari';

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::CARI_GORUNTULE;
    }

    /**
     * @return array<int, string>
     */
    protected static function muhasebeSayfasiYetkiKodlari(): array
    {
        return [
            MuhasebeYetkiSablonlari::CARI_GORUNTULE,
            MuhasebeYetkiSablonlari::CARI_GUNCELLE,
        ];
    }
}
