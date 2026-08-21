<?php

namespace App\Filament\Clusters\MasrafTakip\Kaynaklar;

use App\Support\MasrafTakipYetkiSablonlari;

trait MasrafTakipSayfaErisimleri
{
    public static function canAccess(): bool
    {
        return MasrafTakipFilamentErisimYardimcisi::herhangiBirMasrafTakipYetkisiVarMi([
            MasrafTakipYetkiSablonlari::GORUNTULE,
            MasrafTakipYetkiSablonlari::OLUSTUR,
            MasrafTakipYetkiSablonlari::GUNCELLE,
            MasrafTakipYetkiSablonlari::SIL,
        ]);
    }
}
