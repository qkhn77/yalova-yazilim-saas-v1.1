<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages;

use App\TeknikServis\Filament\TeknikServisListePreset;

class TeknikServisKayitlariAcikSayfasi extends TeknikServisKayitListesiOrtak
{
    protected static ?string $title = 'Açık servis kayıtları';

    protected static ?string $navigationLabel = 'Açık';

    protected static ?int $navigationSort = 12;

    protected static function listePreseti(): TeknikServisListePreset
    {
        return TeknikServisListePreset::Acik;
    }
}
