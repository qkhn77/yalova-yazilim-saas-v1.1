<?php

namespace App\Filament\Clusters\Restoran\Resources\RestoranMenuKategoriKaynagi\Pages;

use App\Filament\Clusters\Restoran\Resources\RestoranMenuKategoriKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRestoranMenuKategorileri extends ListRecords
{
    protected static string $resource = RestoranMenuKategoriKaynagi::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
