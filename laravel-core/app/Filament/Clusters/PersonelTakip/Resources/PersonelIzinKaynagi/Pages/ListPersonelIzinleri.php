<?php

namespace App\Filament\Clusters\PersonelTakip\Resources\PersonelIzinKaynagi\Pages;

use App\Filament\Clusters\PersonelTakip\Resources\PersonelIzinKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPersonelIzinleri extends ListRecords
{
    protected static string $resource = PersonelIzinKaynagi::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
