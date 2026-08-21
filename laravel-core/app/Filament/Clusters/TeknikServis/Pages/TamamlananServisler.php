<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

use App\Filament\Clusters\TeknikServis\TeknikServisTaslakSayfa;

class TamamlananServisler extends TeknikServisTaslakSayfa
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Tamamlanan Servisler';

    protected static ?string $slug = 'servis-kayit/tamamlanan-servisler';
}
