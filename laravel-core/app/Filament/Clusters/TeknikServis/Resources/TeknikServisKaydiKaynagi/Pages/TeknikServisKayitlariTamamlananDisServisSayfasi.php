<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages;

use App\TeknikServis\Filament\TeknikServisListePreset;

class TeknikServisKayitlariTamamlananDisServisSayfasi extends TeknikServisKayitListesiOrtak
{
    protected static ?string $title = "Tamamlanan d\u{0131}\u{015F} servis kay\u{0131}tlar\u{0131}";

    protected static ?string $navigationLabel = "Tamamlanan d\u{0131}\u{015F} servis";

    protected static ?int $navigationSort = 18;

    protected static function listePreseti(): TeknikServisListePreset
    {
        return TeknikServisListePreset::TamamlananDisServis;
    }
}
