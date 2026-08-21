<?php

namespace App\Filament\Clusters\Muhasebe\Resources\PosHesabiKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\PosHesabiKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPosHesaplari extends ListRecords
{
    protected static string $resource = PosHesabiKaynagi::class;

    public function getSubNavigation(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('POS ekle'),
        ];
    }
}
