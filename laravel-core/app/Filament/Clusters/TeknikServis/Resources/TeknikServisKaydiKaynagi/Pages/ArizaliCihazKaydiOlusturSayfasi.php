<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages;

use App\TeknikServis\Enumlar\ServisTipi;

class ArizaliCihazKaydiOlusturSayfasi extends OrtakTeknikServisKaydiOlustur
{
    protected static ?string $title = "Ar\u{0131}zal\u{0131} cihaz kayd\u{0131} olu\u{015F}tur";

    protected static function sabitServisTipi(): ServisTipi
    {
        return ServisTipi::ArizaliCihaz;
    }
}
