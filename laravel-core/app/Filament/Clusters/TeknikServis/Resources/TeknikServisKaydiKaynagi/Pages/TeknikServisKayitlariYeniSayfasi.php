<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages;

use App\TeknikServis\Filament\TeknikServisListePreset;

class TeknikServisKayitlariYeniSayfasi extends TeknikServisKayitListesiOrtak
{
    protected static ?string $title = 'Yeni servis kayıtları';

    protected static ?string $navigationLabel = 'Yeni';

    protected static ?int $navigationSort = 11;

    protected static function listePreseti(): TeknikServisListePreset
    {
        return TeknikServisListePreset::Yeni;
    }
}
