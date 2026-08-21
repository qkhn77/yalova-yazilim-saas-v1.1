<?php

namespace App\Filament\Clusters\PersonelTakip\Resources\PersonelMaasDonemiKaynagi\Pages;

use App\Filament\Clusters\PersonelTakip\Resources\PersonelMaasDonemiKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPersonelMaasDonemleri extends ListRecords
{
    protected static string $resource = PersonelMaasDonemiKaynagi::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
