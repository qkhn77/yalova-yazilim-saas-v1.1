<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisAksesuarKaynagi\Pages;

use App\Filament\Clusters\TeknikServis\Resources\TeknikServisAksesuarKaynagi;
use App\Filament\Clusters\TeknikServis\Resources\Concerns\HizliTanimListesiSayfasi;

class ListTeknikServisAksesuarlari extends HizliTanimListesiSayfasi
{
    protected static string $resource = TeknikServisAksesuarKaynagi::class;

    protected static ?string $title = 'Aksesuar tanımları';
}
