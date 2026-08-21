<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeMarkaTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeMarkaTanimKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMuhasebeMarkalari extends ListRecords
{
    protected static string $resource = MuhasebeMarkaTanimKaynagi::class;

    protected static ?string $title = 'Ürün Markaları';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Ürün markası ekle'),
        ];
    }
}
