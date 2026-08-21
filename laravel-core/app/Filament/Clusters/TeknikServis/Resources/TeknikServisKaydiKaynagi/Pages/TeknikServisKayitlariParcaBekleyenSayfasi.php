<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages;

use App\TeknikServis\Filament\TeknikServisListePreset;

class TeknikServisKayitlariParcaBekleyenSayfasi extends TeknikServisKayitListesiOrtak
{
    protected static ?string $title = 'Parça bekleyen kayıtlar';

    protected static ?string $navigationLabel = 'Parça bekleyen';

    protected static ?int $navigationSort = 14;

    protected static function listePreseti(): TeknikServisListePreset
    {
        return TeknikServisListePreset::ParcaBekleyen;
    }
}
