<?php

namespace App\Filament\Clusters\Restoran\Resources\RestoranAdisyonKaynagi\Pages;

use App\Filament\Clusters\Restoran\Resources\RestoranAdisyonKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRestoranAdisyonlari extends ListRecords
{
    protected static string $resource = RestoranAdisyonKaynagi::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
