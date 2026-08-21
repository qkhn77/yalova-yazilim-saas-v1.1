<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages;

use App\TeknikServis\Filament\TeknikServisListePreset;

class TeknikServisKayitlariTezgahtaSayfasi extends TeknikServisKayitListesiOrtak
{
    protected static ?string $title = 'Tezgahtaki kayıtlar';

    protected static ?string $navigationLabel = 'Tezgahta';

    protected static ?int $navigationSort = 13;

    protected static function listePreseti(): TeknikServisListePreset
    {
        return TeknikServisListePreset::Tezgahta;
    }
}
