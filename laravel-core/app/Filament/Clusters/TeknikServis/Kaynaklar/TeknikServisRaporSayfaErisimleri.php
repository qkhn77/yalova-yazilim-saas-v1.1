<?php

namespace App\Filament\Clusters\TeknikServis\Kaynaklar;

use App\Support\TeknikServisYetkiSablonlari;

/**
 * TS rapor sayfaları: rapor yetkisi veya genel görüntüleme (geriye dönük uyum).
 */
trait TeknikServisRaporSayfaErisimleri
{
    public static function canAccess(): bool
    {
        return TeknikServisFilamentErisimYardimcisi::herhangiBirTeknikServisErisimiVarMi([
            TeknikServisYetkiSablonlari::RAPOR_GORUNTULE,
            TeknikServisYetkiSablonlari::GORUNTULE,
        ]);
    }
}
