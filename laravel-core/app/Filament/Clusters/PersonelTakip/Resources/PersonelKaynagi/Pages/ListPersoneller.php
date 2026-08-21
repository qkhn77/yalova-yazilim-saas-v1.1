<?php

namespace App\Filament\Clusters\PersonelTakip\Resources\PersonelKaynagi\Pages;

use App\Filament\Clusters\PersonelTakip\Resources\PersonelKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPersoneller extends ListRecords
{
    protected static string $resource = PersonelKaynagi::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
