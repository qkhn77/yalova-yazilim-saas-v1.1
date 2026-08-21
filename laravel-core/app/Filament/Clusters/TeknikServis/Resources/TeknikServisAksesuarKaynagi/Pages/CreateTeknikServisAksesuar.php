<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisAksesuarKaynagi\Pages;

use App\Filament\Clusters\TeknikServis\Resources\TeknikServisAksesuarKaynagi;
use App\Filament\Clusters\TeknikServis\Resources\Concerns\HizliTanimOlusturSayfasi;

class CreateTeknikServisAksesuar extends HizliTanimOlusturSayfasi
{
    protected static string $resource = TeknikServisAksesuarKaynagi::class;
}
