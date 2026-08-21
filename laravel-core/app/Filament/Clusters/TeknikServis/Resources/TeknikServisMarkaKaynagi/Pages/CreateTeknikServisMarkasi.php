<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisMarkaKaynagi\Pages;

use App\Filament\Clusters\TeknikServis\Resources\TeknikServisMarkaKaynagi;
use App\Filament\Clusters\TeknikServis\Resources\Concerns\HizliTanimOlusturSayfasi;

class CreateTeknikServisMarkasi extends HizliTanimOlusturSayfasi
{
    protected static string $resource = TeknikServisMarkaKaynagi::class;
}
