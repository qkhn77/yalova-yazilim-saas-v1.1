<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\VergiOraniTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\VergiOraniTanimKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVergiOranlari extends ListRecords
{
    protected static string $resource = VergiOraniTanimKaynagi::class;

    protected static ?string $title = 'Vergi oranları';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Vergi oranı ekle'),
        ];
    }
}
