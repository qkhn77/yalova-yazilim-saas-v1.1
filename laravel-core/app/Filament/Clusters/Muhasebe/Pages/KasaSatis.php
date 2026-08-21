<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Pages\Page;

class KasaSatis extends Page
{
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = Muhasebe::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Kasa Satış';

    protected static ?string $slug = 'satis/kasa-satis';

    protected static string $view = 'filament.clusters.muhasebe.pages.kasa-satis';

    public function mount(): void
    {
        $this->redirect(FinansHareketleriListesiSayfasi::getUrl());
    }

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::FINANS_GORUNTULE;
    }

    /**
     * @return array<int, string>
     */
    protected static function muhasebeSayfasiYetkiKodlari(): array
    {
        return [
            MuhasebeYetkiSablonlari::FINANS_GORUNTULE,
            MuhasebeYetkiSablonlari::FINANS_OLUSTUR,
            MuhasebeYetkiSablonlari::FINANS_GUNCELLE,
        ];
    }
}
