<?php

namespace App\Filament\Resources\FirmaKullaniciGrubuKaynagi\Pages;

use App\Filament\Resources\FirmaKullaniciGrubuKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFirmaKullaniciGruplari extends ListRecords
{
    protected static string $resource = FirmaKullaniciGrubuKaynagi::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Firma Kullanıcı Grubu Ekle'),
        ];
    }
}
