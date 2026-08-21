<?php

namespace App\Filament\Clusters\Muhasebe\Resources\BankaHesabiKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\BankaHesabiKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBankaHesaplari extends ListRecords
{
    protected static string $resource = BankaHesabiKaynagi::class;

    public function getSubNavigation(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Banka ekle'),
        ];
    }
}
