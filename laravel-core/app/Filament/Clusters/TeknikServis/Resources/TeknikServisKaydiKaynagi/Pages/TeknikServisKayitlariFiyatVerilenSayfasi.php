<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages;

use App\TeknikServis\Filament\TeknikServisListePreset;

class TeknikServisKayitlariFiyatVerilenSayfasi extends TeknikServisKayitListesiOrtak
{
    protected static ?string $title = 'Fiyat verilen kayıtlar';

    protected static ?string $navigationLabel = 'Fiyat verilen';

    protected static ?int $navigationSort = 16;

    protected static function listePreseti(): TeknikServisListePreset
    {
        return TeknikServisListePreset::FiyatVerilen;
    }
}
