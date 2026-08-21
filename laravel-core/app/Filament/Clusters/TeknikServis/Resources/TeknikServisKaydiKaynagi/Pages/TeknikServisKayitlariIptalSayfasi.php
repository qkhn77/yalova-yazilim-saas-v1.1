<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages;

use App\TeknikServis\Filament\TeknikServisListePreset;

class TeknikServisKayitlariIptalSayfasi extends TeknikServisKayitListesiOrtak
{
    protected static ?string $title = 'İptal edilen kayıtlar';

    protected static ?string $navigationLabel = 'İptal';

    protected static ?int $navigationSort = 20;

    protected static function listePreseti(): TeknikServisListePreset
    {
        return TeknikServisListePreset::Iptal;
    }
}
