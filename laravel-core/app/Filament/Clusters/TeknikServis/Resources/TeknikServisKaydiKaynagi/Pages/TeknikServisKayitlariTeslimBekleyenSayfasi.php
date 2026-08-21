<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages;

use App\TeknikServis\Filament\TeknikServisListePreset;

class TeknikServisKayitlariTeslimBekleyenSayfasi extends TeknikServisKayitListesiOrtak
{
    protected static ?string $title = 'Teslim bekleyen kayıtlar';

    protected static ?string $navigationLabel = 'Teslim bekleyen';

    protected static ?int $navigationSort = 17;

    protected static function listePreseti(): TeknikServisListePreset
    {
        return TeknikServisListePreset::TeslimBekleyen;
    }
}
