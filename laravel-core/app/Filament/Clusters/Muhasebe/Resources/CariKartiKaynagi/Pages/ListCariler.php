<?php

namespace App\Filament\Clusters\Muhasebe\Resources\CariKartiKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\CariKartiKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCariler extends ListRecords
{
    protected static string $resource = CariKartiKaynagi::class;

    protected static ?string $title = 'Cariler';

    public function getSubNavigation(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Cari ekle'),
        ];
    }
}
