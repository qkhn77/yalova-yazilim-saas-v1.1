<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeLogoTuruTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeLogoTuruTanimKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMuhasebeLogoTurleri extends ListRecords
{
    protected static string $resource = MuhasebeLogoTuruTanimKaynagi::class;

    protected static ?string $title = 'Logo türleri';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Logo türü ekle'),
        ];
    }
}
