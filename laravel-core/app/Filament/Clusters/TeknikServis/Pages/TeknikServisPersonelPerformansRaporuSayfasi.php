<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

use App\Filament\Clusters\TeknikServis\Kaynaklar\TeknikServisRaporSayfaErisimleri;
use App\Filament\Clusters\TeknikServis\TeknikServisRaporSayfasi;
use App\TeknikServis\Servisler\TeknikServisRaporServisi;
use Illuminate\Support\Carbon;

class TeknikServisPersonelPerformansRaporuSayfasi extends TeknikServisRaporSayfasi
{
    use TeknikServisRaporSayfaErisimleri;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $title = 'Personel performans raporu';

    protected static ?string $navigationLabel = 'Personel performansı';

    protected static ?string $navigationGroup = 'Raporlar';

    protected static ?int $navigationSort = 61;

    protected static ?string $slug = 'raporlar/personel-performans';

    /**
     * @return array<string, mixed>
     */
    protected function raporVerisi(int $firmaId, Carbon $baslangic, Carbon $bitis): array
    {
        return app(TeknikServisRaporServisi::class)->personelPerformansi($firmaId, $baslangic, $bitis);
    }
}
