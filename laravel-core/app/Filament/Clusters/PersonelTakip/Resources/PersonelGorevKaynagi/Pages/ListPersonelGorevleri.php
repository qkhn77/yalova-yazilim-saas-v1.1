<?php

namespace App\Filament\Clusters\PersonelTakip\Resources\PersonelGorevKaynagi\Pages;

use App\Filament\Clusters\PersonelTakip\Resources\PersonelGorevKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPersonelGorevleri extends ListRecords
{
    protected static string $resource = PersonelGorevKaynagi::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
