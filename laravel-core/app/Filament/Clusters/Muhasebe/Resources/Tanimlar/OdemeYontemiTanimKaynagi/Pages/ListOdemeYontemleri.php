<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\OdemeYontemiTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\OdemeYontemiTanimKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOdemeYontemleri extends ListRecords
{
    protected static string $resource = OdemeYontemiTanimKaynagi::class;

    protected static ?string $title = 'Ödeme yöntemleri';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Ödeme yöntemi ekle'),
        ];
    }
}
