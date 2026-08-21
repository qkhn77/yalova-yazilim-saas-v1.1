<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages;

use App\TeknikServis\Filament\TeknikServisListePreset;

class TeknikServisKayitlariIadeSayfasi extends TeknikServisKayitListesiOrtak
{
    protected static ?string $title = 'İade kayıtları';

    protected static ?string $navigationLabel = 'İade';

    protected static ?int $navigationSort = 21;

    protected static function listePreseti(): TeknikServisListePreset
    {
        return TeknikServisListePreset::Iade;
    }
}
