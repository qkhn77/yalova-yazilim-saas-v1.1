<?php

namespace App\Filament\Clusters\PersonelTakip\Resources\PersonelVardiyaKaynagi\Pages;

use App\Filament\Clusters\PersonelTakip\Resources\PersonelVardiyaKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPersonelVardiyalari extends ListRecords
{
    protected static string $resource = PersonelVardiyaKaynagi::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
