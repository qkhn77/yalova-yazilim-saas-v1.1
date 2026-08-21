<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeTasarimTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeTasarimTanimKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMuhasebeTasarimlari extends ListRecords
{
    protected static string $resource = MuhasebeTasarimTanimKaynagi::class;

    protected static ?string $title = 'Tasarımlar';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Tasarım ekle'),
        ];
    }
}
