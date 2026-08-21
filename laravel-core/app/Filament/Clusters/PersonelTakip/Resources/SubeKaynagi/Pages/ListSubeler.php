<?php

namespace App\Filament\Clusters\PersonelTakip\Resources\SubeKaynagi\Pages;

use App\Filament\Clusters\PersonelTakip\Resources\SubeKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSubeler extends ListRecords
{
    protected static string $resource = SubeKaynagi::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
