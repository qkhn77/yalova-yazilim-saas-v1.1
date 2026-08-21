<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeStokModeliTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeStokModeliTanimKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMuhasebeStokModelleri extends ListRecords
{
    protected static string $resource = MuhasebeStokModeliTanimKaynagi::class;

    protected static ?string $title = 'Stok modelleri';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Ürün modeli ekle'),
        ];
    }
}
