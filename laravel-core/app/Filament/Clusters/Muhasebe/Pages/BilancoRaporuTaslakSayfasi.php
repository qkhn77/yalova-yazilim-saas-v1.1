<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe\MuhasebeTaslakSayfa;
use App\Support\MuhasebeYetkiSablonlari;

class BilancoRaporuTaslakSayfasi extends MuhasebeTaslakSayfa
{
    protected static ?string $title = 'Bilanço Raporu';

    protected static ?string $slug = 'raporlar/bilanco';

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::RAPOR_GORUNTULE;
    }
}
