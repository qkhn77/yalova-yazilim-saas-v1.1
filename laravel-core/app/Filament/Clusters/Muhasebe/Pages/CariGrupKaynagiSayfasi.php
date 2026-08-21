<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe\MuhasebeTaslakSayfa;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\CariGrubuTanimKaynagi;
use App\Support\MuhasebeYetkiSablonlari;

class CariGrupKaynagiSayfasi extends MuhasebeTaslakSayfa
{
    /** @deprecated STEP 15.1.1 — yerine {@see CariGrubuTanimKaynagi} */
    public static function isDiscovered(): bool
    {
        return false;
    }

    protected static ?string $title = 'Cari Grupları';

    protected static ?string $slug = 'tanimlar/cari-gruplari';

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::TANIM_GORUNTULE;
    }
}
