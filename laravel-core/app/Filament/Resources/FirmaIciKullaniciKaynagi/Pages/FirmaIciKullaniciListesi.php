<?php

namespace App\Filament\Resources\FirmaIciKullaniciKaynagi\Pages;

use App\Filament\Resources\FirmaIciKullaniciKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class FirmaIciKullaniciListesi extends ListRecords
{
    protected static string $resource = FirmaIciKullaniciKaynagi::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Kullanıcı ekle'),
        ];
    }
}
