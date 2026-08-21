<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages;

use App\TeknikServis\Enumlar\ServisTipi;

class BakimKaydiOlusturSayfasi extends OrtakTeknikServisKaydiOlustur
{
    protected static ?string $title = "Bak\u{0131}m kayd\u{0131} olu\u{015F}tur";

    protected static function sabitServisTipi(): ServisTipi
    {
        return ServisTipi::Bakim;
    }
}
