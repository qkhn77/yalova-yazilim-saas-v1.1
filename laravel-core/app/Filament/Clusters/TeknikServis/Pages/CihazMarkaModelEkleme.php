<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

use App\Filament\Clusters\TeknikServis\TeknikServisTaslakSayfa;

class CihazMarkaModelEkleme extends TeknikServisTaslakSayfa
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Cihaz / Marka / Model Ekleme';

    protected static ?string $slug = 'ayarlar/cihaz-marka-model-ekleme';
}
