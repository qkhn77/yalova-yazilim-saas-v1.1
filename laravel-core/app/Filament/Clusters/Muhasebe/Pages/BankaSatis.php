<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Pages\Page;

class BankaSatis extends Page
{
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = Muhasebe::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Banka Satış';

    protected static ?string $slug = 'satis/banka-satis';

    protected static string $view = 'filament.clusters.muhasebe.pages.banka-satis';

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
