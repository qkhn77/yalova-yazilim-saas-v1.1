<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisCihazKaynagi\Pages;

use App\Filament\Clusters\TeknikServis\Resources\TeknikServisCihazKaynagi;
use App\Filament\Clusters\TeknikServis\Resources\Concerns\HizliTanimListesiSayfasi;

class ListTeknikServisCihazlari extends HizliTanimListesiSayfasi
{
    protected static string $resource = TeknikServisCihazKaynagi::class;

    protected static ?string $title = 'Cihaz tanımları';
}
