<?php

namespace App\Filament\Resources\ModulYonetimKaynagi\Pages;

use App\Filament\Resources\ModulYonetimKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ModulListesi extends ListRecords
{
    protected static string $resource = ModulYonetimKaynagi::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Modül ekle'),
        ];
    }
}
