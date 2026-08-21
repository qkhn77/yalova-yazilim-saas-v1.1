<?php

namespace App\Filament\Clusters\Restoran\Resources\RestoranMasaKaynagi\Pages;

use App\Filament\Clusters\Restoran\Resources\RestoranMasaKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRestoranMasalari extends ListRecords
{
    protected static string $resource = RestoranMasaKaynagi::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
