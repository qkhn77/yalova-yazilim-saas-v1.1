<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe\MuhasebeTaslakSayfa;
use App\Support\MuhasebeYetkiSablonlari;

class KarZararRaporuTaslakSayfasi extends MuhasebeTaslakSayfa
{
    protected static ?string $title = 'Kar / Zarar Raporu';

    protected static ?string $slug = 'raporlar/kar-zarar';

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::RAPOR_GORUNTULE;
    }
}
