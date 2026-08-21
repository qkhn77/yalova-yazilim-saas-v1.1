<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages;

use App\TeknikServis\Enumlar\ServisTipi;

class DisServisKaydiOlusturSayfasi extends OrtakTeknikServisKaydiOlustur
{
    protected static ?string $title = "D\u{0131}\u{015F} servis kayd\u{0131} olu\u{015F}tur";

    protected static function sabitServisTipi(): ServisTipi
    {
        return ServisTipi::DisServis;
    }
}
