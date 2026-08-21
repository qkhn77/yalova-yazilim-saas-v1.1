<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Pages\Page;

class PosSatis extends Page
{
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = Muhasebe::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'POS Satış';

    protected static ?string $slug = 'satis/pos-satis';

    protected static string $view = 'filament.clusters.muhasebe.pages.pos-satis';

    public function mount(): void
    {
        $this->redirect(FinansHareketleriListesiSayfasi::getUrl());
    }

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::FINANS_GORUNTULE;
    }

    /**
     * Eski POS satış URL’si; erişim finans veya POS yetkisi olanlara açık, içerik Finans hareketlerine yönlendirilir.
     *
     * @return array<int, string>
     */
    protected static function muhasebeSayfasiYetkiKodlari(): array
    {
        return [
            MuhasebeYetkiSablonlari::FINANS_GORUNTULE,
            MuhasebeYetkiSablonlari::FINANS_OLUSTUR,
            MuhasebeYetkiSablonlari::FINANS_GUNCELLE,
            MuhasebeYetkiSablonlari::POS_GORUNTULE,
            MuhasebeYetkiSablonlari::POS_OLUSTUR,
            MuhasebeYetkiSablonlari::POS_GUNCELLE,
        ];
    }
}
