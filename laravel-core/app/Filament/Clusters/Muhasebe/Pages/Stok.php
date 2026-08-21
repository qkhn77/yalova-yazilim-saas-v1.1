<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Pages\Page;

class Stok extends Page
{
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = Muhasebe::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Stok';

    protected static ?string $slug = 'stok';

    protected static string $view = 'filament.clusters.muhasebe.pages.stok';

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::STOK_GORUNTULE;
    }

    /**
     * @return array<int, string>
     */
    protected static function muhasebeSayfasiYetkiKodlari(): array
    {
        return [
            MuhasebeYetkiSablonlari::STOK_GORUNTULE,
            MuhasebeYetkiSablonlari::STOK_OLUSTUR,
            MuhasebeYetkiSablonlari::STOK_GUNCELLE,
            MuhasebeYetkiSablonlari::STOK_SIL,
        ];
    }
}
