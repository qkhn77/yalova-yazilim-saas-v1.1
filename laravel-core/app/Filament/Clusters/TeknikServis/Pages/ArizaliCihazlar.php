<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

use App\Filament\Clusters\TeknikServis\TeknikServisTaslakSayfa;

class ArizaliCihazlar extends TeknikServisTaslakSayfa
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Arızalı Cihaz Listesi';

    protected static ?string $slug = 'cihaz-kayit/arizali-cihazlar';
}
