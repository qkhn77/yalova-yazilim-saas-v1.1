<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

use App\Filament\Clusters\TeknikServis\TeknikServisTaslakSayfa;

class AksesuarEkleme extends TeknikServisTaslakSayfa
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Aksesuar Ekleme';

    protected static ?string $slug = 'ayarlar/aksesuar-ekleme';
}
