<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\CariGrubuTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\CariGrubuTanimKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCariGruplari extends ListRecords
{
    protected static string $resource = CariGrubuTanimKaynagi::class;

    protected static ?string $title = 'Cari grupları';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Cari grubu ekle'),
        ];
    }
}
