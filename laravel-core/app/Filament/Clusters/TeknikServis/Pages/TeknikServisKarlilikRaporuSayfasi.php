<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

use App\Filament\Clusters\TeknikServis\Kaynaklar\TeknikServisRaporSayfaErisimleri;
use App\Filament\Clusters\TeknikServis\TeknikServisRaporSayfasi;
use App\TeknikServis\Servisler\TeknikServisRaporServisi;
use Illuminate\Support\Carbon;

class TeknikServisKarlilikRaporuSayfasi extends TeknikServisRaporSayfasi
{
    use TeknikServisRaporSayfaErisimleri;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $title = 'Karlılık raporu';

    protected static ?string $navigationLabel = 'Karlılık raporu';

    protected static ?string $navigationGroup = 'Raporlar';

    protected static ?int $navigationSort = 60;

    protected static ?string $slug = 'raporlar/karlilik';

    /**
     * @return array<string, mixed>
     */
    protected function raporVerisi(int $firmaId, Carbon $baslangic, Carbon $bitis): array
    {
        return app(TeknikServisRaporServisi::class)->karlilik($firmaId, $baslangic, $bitis);
    }
}
