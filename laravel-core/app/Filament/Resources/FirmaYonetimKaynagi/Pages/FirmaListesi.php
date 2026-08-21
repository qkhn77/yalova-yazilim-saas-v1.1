<?php

namespace App\Filament\Resources\FirmaYonetimKaynagi\Pages;

use App\Filament\Resources\FirmaYonetimKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class FirmaListesi extends ListRecords
{
    protected static string $resource = FirmaYonetimKaynagi::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Firma ekle'),
        ];
    }
}
