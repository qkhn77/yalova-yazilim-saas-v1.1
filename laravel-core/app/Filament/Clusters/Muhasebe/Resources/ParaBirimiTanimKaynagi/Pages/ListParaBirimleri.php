<?php

namespace App\Filament\Clusters\Muhasebe\Resources\ParaBirimiTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\ParaBirimiTanimKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListParaBirimleri extends ListRecords
{
    protected static string $resource = ParaBirimiTanimKaynagi::class;

    protected static ?string $title = 'Para birimleri';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Para birimi ekle'),
        ];
    }
}
