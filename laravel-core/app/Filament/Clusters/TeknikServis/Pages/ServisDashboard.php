<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

use App\Filament\Clusters\TeknikServis\TeknikServisTaslakSayfa;

class ServisDashboard extends TeknikServisTaslakSayfa
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Servis Dashboard';

    protected static ?string $slug = 'servis-dashboard';
}
