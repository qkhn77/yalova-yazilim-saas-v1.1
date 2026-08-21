<?php

namespace App\Filament\Clusters\Restoran\Resources\RestoranMenuUrunKaynagi\Pages;

use App\Filament\Clusters\Restoran\Resources\RestoranMenuUrunKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRestoranMenuUrunleri extends ListRecords
{
    protected static string $resource = RestoranMenuUrunKaynagi::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
