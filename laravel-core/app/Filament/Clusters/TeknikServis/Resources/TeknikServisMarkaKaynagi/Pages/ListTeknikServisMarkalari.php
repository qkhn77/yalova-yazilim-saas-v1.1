<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisMarkaKaynagi\Pages;

use App\Filament\Clusters\TeknikServis\Resources\TeknikServisMarkaKaynagi;
use App\Filament\Clusters\TeknikServis\Resources\Concerns\HizliTanimListesiSayfasi;

class ListTeknikServisMarkalari extends HizliTanimListesiSayfasi
{
    protected static string $resource = TeknikServisMarkaKaynagi::class;

    protected static ?string $title = 'Marka tanımları';
}
