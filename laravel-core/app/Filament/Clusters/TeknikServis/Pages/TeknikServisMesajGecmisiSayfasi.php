<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

use App\Filament\Clusters\TeknikServis\Kaynaklar\TeknikServisSayfaErisimleri;
use App\Filament\Clusters\TeknikServis\TeknikServisTaslakSayfa;

class TeknikServisMesajGecmisiSayfasi extends TeknikServisTaslakSayfa
{
    use TeknikServisSayfaErisimleri;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $title = "Mesaj ge\u{00E7}mi\u{015F}i";

    protected static ?string $navigationLabel = "Mesaj ge\u{00E7}mi\u{015F}i";

    protected static ?string $navigationGroup = 'Operasyon';

    protected static ?int $navigationSort = 51;

    protected static ?string $slug = 'operasyon/mesaj-gecmisi';
}
