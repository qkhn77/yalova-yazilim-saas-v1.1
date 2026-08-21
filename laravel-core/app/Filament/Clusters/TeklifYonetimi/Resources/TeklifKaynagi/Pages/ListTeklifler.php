<?php

namespace App\Filament\Clusters\TeklifYonetimi\Resources\TeklifKaynagi\Pages;

use App\Filament\Clusters\TeklifYonetimi\Resources\TeklifKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTeklifler extends ListRecords
{
    protected static string $resource = TeklifKaynagi::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Yeni teklif'),
        ];
    }
}
