<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages;

use App\TeknikServis\Filament\TeknikServisListePreset;

class ListTeknikServisKayitlari extends TeknikServisKayitListesiOrtak
{
    protected static ?string $title = 'Tüm servis kayıtları';

    protected static ?string $navigationLabel = 'Tüm kayıtlar';

    protected static ?int $navigationSort = 10;

    protected static function listePreseti(): TeknikServisListePreset
    {
        return TeknikServisListePreset::Tum;
    }
}
