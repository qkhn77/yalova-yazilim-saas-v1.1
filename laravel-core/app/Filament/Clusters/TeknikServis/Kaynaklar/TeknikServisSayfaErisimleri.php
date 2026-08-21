<?php

namespace App\Filament\Clusters\TeknikServis\Kaynaklar;

use App\Support\TeknikServisYetkiSablonlari;

/**
 * Özet ve operasyon sayfaları için {@see \Filament\Pages\Page::canAccess}.
 */
trait TeknikServisSayfaErisimleri
{
    public static function canAccess(): bool
    {
        return TeknikServisFilamentErisimYardimcisi::herhangiBirTeknikServisErisimiVarMi(
            TeknikServisYetkiSablonlari::panelOzetiVeOperasyonYetkileri()
        );
    }
}
