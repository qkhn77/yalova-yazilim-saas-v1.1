<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages;

use App\TeknikServis\Filament\TeknikServisListePreset;

class TeknikServisKayitlariGarantiyeGonderilenSayfasi extends TeknikServisKayitListesiOrtak
{
    protected static ?string $title = 'Garantiye gönderilen kayıtlar';

    protected static ?string $navigationLabel = 'Garantiye gönderilen';

    protected static ?int $navigationSort = 15;

    protected static function listePreseti(): TeknikServisListePreset
    {
        return TeknikServisListePreset::GarantiyeGonderilen;
    }
}
