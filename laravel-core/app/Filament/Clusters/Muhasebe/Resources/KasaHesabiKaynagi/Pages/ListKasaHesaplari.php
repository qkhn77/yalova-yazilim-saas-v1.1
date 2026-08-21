<?php

namespace App\Filament\Clusters\Muhasebe\Resources\KasaHesabiKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\KasaHesabiKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKasaHesaplari extends ListRecords
{
    protected static string $resource = KasaHesabiKaynagi::class;

    public function getSubNavigation(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Kasa ekle'),
        ];
    }
}
