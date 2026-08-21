<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

use App\Filament\Clusters\TeknikServis\TeknikServisTaslakSayfa;

class TeslimEdilenCihazlar extends TeknikServisTaslakSayfa
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Teslim Edilen Cihazlar';

    protected static ?string $slug = 'cihaz-kayit/teslim-edilen-cihazlar';
}
