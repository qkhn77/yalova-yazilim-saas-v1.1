<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisDurumuTanimKaynagi\Pages;

use App\Filament\Clusters\TeknikServis\Resources\TeknikServisDurumuTanimKaynagi;
use App\Filament\Clusters\TeknikServis\Resources\Concerns\HizliTanimListesiSayfasi;

class ListTeknikServisDurumlari extends HizliTanimListesiSayfasi
{
    protected static string $resource = TeknikServisDurumuTanimKaynagi::class;

    protected static ?string $title = 'Servis durumları';
}
