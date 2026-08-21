<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeVaryantTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeVaryantTanimKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMuhasebeVaryantlari extends ListRecords
{
    protected static string $resource = MuhasebeVaryantTanimKaynagi::class;

    protected static ?string $title = 'Varyantlar';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Varyant ekle'),
        ];
    }
}
