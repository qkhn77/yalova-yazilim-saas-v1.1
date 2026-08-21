<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisCihazKaynagi\Pages;

use App\Filament\Clusters\TeknikServis\Resources\TeknikServisCihazKaynagi;
use App\Filament\Clusters\TeknikServis\Resources\Concerns\HizliTanimOlusturSayfasi;

class CreateTeknikServisCihazi extends HizliTanimOlusturSayfasi
{
    protected static string $resource = TeknikServisCihazKaynagi::class;
}
