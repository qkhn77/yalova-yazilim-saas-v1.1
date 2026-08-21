<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

use App\Filament\Clusters\TeknikServis\Kaynaklar\TeknikServisRaporSayfaErisimleri;
use App\Filament\Clusters\TeknikServis\TeknikServisRaporSayfasi;
use App\TeknikServis\Servisler\TeknikServisRaporServisi;
use Illuminate\Support\Carbon;

class TeknikServisTahsilatServisRaporuSayfasi extends TeknikServisRaporSayfasi
{
    use TeknikServisRaporSayfaErisimleri;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $title = 'Tahsilat / servis raporu';

    protected static ?string $navigationLabel = 'Tahsilat & servis';

    protected static ?string $navigationGroup = 'Raporlar';

    protected static ?int $navigationSort = 64;

    protected static ?string $slug = 'raporlar/tahsilat-servis';

    /**
     * @return array<string, mixed>
     */
    protected function raporVerisi(int $firmaId, Carbon $baslangic, Carbon $bitis): array
    {
        return app(TeknikServisRaporServisi::class)->tahsilatServis($firmaId, $baslangic, $bitis);
    }
}
