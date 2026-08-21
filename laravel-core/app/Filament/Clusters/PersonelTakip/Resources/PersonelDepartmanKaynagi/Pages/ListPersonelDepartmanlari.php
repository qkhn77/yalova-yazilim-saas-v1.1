<?php

namespace App\Filament\Clusters\PersonelTakip\Resources\PersonelDepartmanKaynagi\Pages;

use App\Filament\Clusters\PersonelTakip\Resources\PersonelDepartmanKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPersonelDepartmanlari extends ListRecords
{
    protected static string $resource = PersonelDepartmanKaynagi::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
