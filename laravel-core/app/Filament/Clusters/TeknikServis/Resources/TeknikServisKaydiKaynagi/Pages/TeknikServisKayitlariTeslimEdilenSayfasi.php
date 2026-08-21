<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages;

use App\TeknikServis\Filament\TeknikServisListePreset;

class TeknikServisKayitlariTeslimEdilenSayfasi extends TeknikServisKayitListesiOrtak
{
    protected static ?string $title = 'Teslim edilen kayıtlar';

    protected static ?string $navigationLabel = 'Teslim edilen';

    protected static ?int $navigationSort = 19;

    protected static function listePreseti(): TeknikServisListePreset
    {
        return TeknikServisListePreset::TeslimEdilen;
    }
}
