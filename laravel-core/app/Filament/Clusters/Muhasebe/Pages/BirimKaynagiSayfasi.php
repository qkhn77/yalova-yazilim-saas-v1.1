<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe\MuhasebeTaslakSayfa;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\BirimTanimKaynagi;
use App\Support\MuhasebeYetkiSablonlari;

class BirimKaynagiSayfasi extends MuhasebeTaslakSayfa
{
    /** @deprecated STEP 15.1.1 — yerine {@see BirimTanimKaynagi} */
    public static function isDiscovered(): bool
    {
        return false;
    }

    protected static ?string $title = 'Birimler';

    protected static ?string $slug = 'tanimlar/birimler';

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::TANIM_GORUNTULE;
    }
}
