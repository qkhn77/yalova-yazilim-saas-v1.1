<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\BirimTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\BirimTanimKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBirimler extends ListRecords
{
    protected static string $resource = BirimTanimKaynagi::class;

    protected static ?string $title = 'Birimler';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Birim ekle'),
        ];
    }
}
