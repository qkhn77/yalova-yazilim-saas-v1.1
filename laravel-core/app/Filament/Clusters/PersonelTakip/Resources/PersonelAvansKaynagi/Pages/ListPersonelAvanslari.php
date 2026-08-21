<?php

namespace App\Filament\Clusters\PersonelTakip\Resources\PersonelAvansKaynagi\Pages;

use App\Filament\Clusters\PersonelTakip\Resources\PersonelAvansKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPersonelAvanslari extends ListRecords
{
    protected static string $resource = PersonelAvansKaynagi::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
