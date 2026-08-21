<?php

namespace App\Filament\Clusters\TeknikServis\Kaynaklar;

use App\Support\TeknikServisYetkiSablonlari;

/**
 * Genel ayarlar ve şablon sayfaları.
 */
trait TeknikServisAyarSayfaErisimleri
{
    public static function canAccess(): bool
    {
        return TeknikServisFilamentErisimYardimcisi::herhangiBirTeknikServisErisimiVarMi([
            TeknikServisYetkiSablonlari::AYAR_GORUNTULE,
            TeknikServisYetkiSablonlari::AYAR_GUNCELLE,
        ]);
    }
}
