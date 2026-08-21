<?php

namespace App\Filament\Clusters\Muhasebe\Kaynaklar;

use App\Muhasebe\Guvenlik\MuhasebeFilamentErisimYardimcisi;

/**
 * Muhasebe kümesindeki Filament Page sınıfları için canAccess kalıbı.
 * Kullanan sınıf `gerekliYetkiKodu()` tanımlamalıdır.
 *
 * Varsayılan: yalnızca o kod. İsteğe bağlı {@see muhasebeSayfasiYetkiKodlari} ile OR listesi
 * (ör. cari.goruntule | cari.guncelle) — policy / resource ile tutarlılık için.
 */
trait MuhasebeSayfaErisimleri
{
    public static function canAccess(): bool
    {
        foreach (static::muhasebeSayfasiYetkiKodlari() as $yetkiKodu) {
            if (MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi($yetkiKodu)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sayfaya giriş için yeterli olan yetkilerden biri (OR).
     *
     * @return array<int, string>
     */
    protected static function muhasebeSayfasiYetkiKodlari(): array
    {
        return [static::gerekliYetkiKodu()];
    }
}
