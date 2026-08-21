<?php

namespace App\Filament\Clusters\Restoran\Resources\RestoranAdisyonKalemiKaynagi\Pages;

use App\Filament\Clusters\Restoran\Resources\RestoranAdisyonKalemiKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRestoranAdisyonKalemleri extends ListRecords
{
    protected static string $resource = RestoranAdisyonKalemiKaynagi::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
