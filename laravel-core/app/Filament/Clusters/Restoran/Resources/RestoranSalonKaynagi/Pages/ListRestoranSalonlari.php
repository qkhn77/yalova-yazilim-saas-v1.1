<?php

namespace App\Filament\Clusters\Restoran\Resources\RestoranSalonKaynagi\Pages;

use App\Filament\Clusters\Restoran\Resources\RestoranSalonKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRestoranSalonlari extends ListRecords
{
    protected static string $resource = RestoranSalonKaynagi::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
