<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

use App\Filament\Clusters\TeknikServis\Kaynaklar\TeknikServisSayfaErisimleri;
use App\Filament\Clusters\TeknikServis\TeknikServisTaslakSayfa;

class TeknikServisIslemLoglariSayfasi extends TeknikServisTaslakSayfa
{
    use TeknikServisSayfaErisimleri;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $title = "\u{0130}\u{015F}lem loglar\u{0131}";

    protected static ?string $navigationLabel = "\u{0130}\u{015F}lem loglar\u{0131}";

    protected static ?string $navigationGroup = 'Operasyon';

    protected static ?int $navigationSort = 50;

    protected static ?string $slug = 'operasyon/islem-loglari';
}
