<?php

namespace App\Filament\Clusters\PersonelTakip\Resources\PersonelVardiyaSablonuKaynagi\Pages;

use App\Filament\Clusters\PersonelTakip\Resources\PersonelVardiyaSablonuKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPersonelVardiyaSablonlari extends ListRecords
{
    protected static string $resource = PersonelVardiyaSablonuKaynagi::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
