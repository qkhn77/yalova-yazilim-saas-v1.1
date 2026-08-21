<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

use App\Filament\Clusters\TeknikServis\Kaynaklar\TeknikServisRaporSayfaErisimleri;
use App\Filament\Clusters\TeknikServis\TeknikServisRaporSayfasi;
use App\TeknikServis\Servisler\TeknikServisRaporServisi;
use Illuminate\Support\Carbon;

class TeknikServisDurumBazliRaporuSayfasi extends TeknikServisRaporSayfasi
{
    use TeknikServisRaporSayfaErisimleri;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $title = 'Durum bazlı rapor';

    protected static ?string $navigationLabel = 'Durum bazlı rapor';

    protected static ?string $navigationGroup = 'Raporlar';

    protected static ?int $navigationSort = 62;

    protected static ?string $slug = 'raporlar/durum-bazli';

    /**
     * @return array<string, mixed>
     */
    protected function raporVerisi(int $firmaId, Carbon $baslangic, Carbon $bitis): array
    {
        return app(TeknikServisRaporServisi::class)->durumBazli($firmaId, $baslangic, $bitis);
    }
}
