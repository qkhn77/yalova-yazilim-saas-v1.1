<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

use App\Filament\Clusters\TeknikServis\TeknikServisTaslakSayfa;

class ArizaEkleme extends TeknikServisTaslakSayfa
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Arıza Ekleme';

    protected static ?string $slug = 'ayarlar/ariza-ekleme';
}
