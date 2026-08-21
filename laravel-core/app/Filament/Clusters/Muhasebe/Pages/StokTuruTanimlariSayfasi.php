<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe\MuhasebeTaslakSayfa;
use App\Support\MuhasebeYetkiSablonlari;

class StokTuruTanimlariSayfasi extends MuhasebeTaslakSayfa
{
    protected static ?string $title = 'Stok Türleri';

    protected static ?string $slug = 'tanimlar/stok-turleri';

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::TANIM_GORUNTULE;
    }
}
