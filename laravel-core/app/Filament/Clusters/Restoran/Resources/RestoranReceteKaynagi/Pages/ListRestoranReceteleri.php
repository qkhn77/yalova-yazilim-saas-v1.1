<?php

namespace App\Filament\Clusters\Restoran\Resources\RestoranReceteKaynagi\Pages;

use App\Filament\Clusters\Restoran\Resources\RestoranReceteKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRestoranReceteleri extends ListRecords
{
    protected static string $resource = RestoranReceteKaynagi::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
