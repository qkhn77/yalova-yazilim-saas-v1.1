<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

use App\Filament\Clusters\TeknikServis\Kaynaklar\TeknikServisRaporSayfaErisimleri;
use App\Filament\Clusters\TeknikServis\TeknikServisRaporSayfasi;
use App\TeknikServis\Servisler\TeknikServisRaporServisi;
use Illuminate\Support\Carbon;

class TeknikServisGarantiBakimRaporuSayfasi extends TeknikServisRaporSayfasi
{
    use TeknikServisRaporSayfaErisimleri;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $title = 'Garanti / bakım raporu';

    protected static ?string $navigationLabel = 'Garanti & bakım';

    protected static ?string $navigationGroup = 'Raporlar';

    protected static ?int $navigationSort = 63;

    protected static ?string $slug = 'raporlar/garanti-bakim';

    /**
     * @return array<string, mixed>
     */
    protected function raporVerisi(int $firmaId, Carbon $baslangic, Carbon $bitis): array
    {
        return app(TeknikServisRaporServisi::class)->garantiBakim($firmaId, $baslangic, $bitis);
    }
}
