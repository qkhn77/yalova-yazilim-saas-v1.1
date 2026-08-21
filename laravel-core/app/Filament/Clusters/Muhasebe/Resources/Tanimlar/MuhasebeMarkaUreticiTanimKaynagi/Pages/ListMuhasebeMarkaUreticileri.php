<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeMarkaUreticiTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeMarkaUreticiTanimKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMuhasebeMarkaUreticileri extends ListRecords
{
    protected static string $resource = MuhasebeMarkaUreticiTanimKaynagi::class;

    protected static ?string $title = 'Marka Üreticileri';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Marka üretici ekle'),
        ];
    }
}
