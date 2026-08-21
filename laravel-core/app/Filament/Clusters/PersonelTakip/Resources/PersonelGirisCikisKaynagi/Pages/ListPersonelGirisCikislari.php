<?php

namespace App\Filament\Clusters\PersonelTakip\Resources\PersonelGirisCikisKaynagi\Pages;

use App\Filament\Clusters\PersonelTakip\Resources\PersonelGirisCikisKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPersonelGirisCikislari extends ListRecords
{
    protected static string $resource = PersonelGirisCikisKaynagi::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
