<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe\MuhasebeTaslakSayfa;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\VergiOraniTanimKaynagi;
use App\Support\MuhasebeYetkiSablonlari;

class VergiOraniKaynagiSayfasi extends MuhasebeTaslakSayfa
{
    /** @deprecated STEP 15.1.1 — yerine {@see VergiOraniTanimKaynagi} */
    public static function isDiscovered(): bool
    {
        return false;
    }

    protected static ?string $title = 'Vergi Oranları';

    protected static ?string $slug = 'tanimlar/vergi-oranlari';

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::TANIM_GORUNTULE;
    }
}
