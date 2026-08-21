<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

use App\Filament\Clusters\TeknikServis\TeknikServisTaslakSayfa;

class ServisListesi extends TeknikServisTaslakSayfa
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Servis Listesi';

    protected static ?string $slug = 'servis-kayit/servis-listesi';
}
