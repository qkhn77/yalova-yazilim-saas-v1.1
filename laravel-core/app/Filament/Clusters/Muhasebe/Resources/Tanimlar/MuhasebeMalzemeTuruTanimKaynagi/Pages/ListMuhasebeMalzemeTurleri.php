<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeMalzemeTuruTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeMalzemeTuruTanimKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMuhasebeMalzemeTurleri extends ListRecords
{
    protected static string $resource = MuhasebeMalzemeTuruTanimKaynagi::class;

    protected static ?string $title = 'Malzeme türleri';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Malzeme türü ekle'),
        ];
    }
}
