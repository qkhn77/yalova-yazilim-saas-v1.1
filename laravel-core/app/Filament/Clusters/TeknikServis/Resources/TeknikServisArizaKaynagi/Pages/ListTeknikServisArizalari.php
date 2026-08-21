<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisArizaKaynagi\Pages;

use App\Filament\Clusters\TeknikServis\Resources\TeknikServisArizaKaynagi;
use App\Filament\Clusters\TeknikServis\Resources\Concerns\HizliTanimListesiSayfasi;

class ListTeknikServisArizalari extends HizliTanimListesiSayfasi
{
    protected static string $resource = TeknikServisArizaKaynagi::class;

    protected static ?string $title = 'Arıza tanımları';
}
