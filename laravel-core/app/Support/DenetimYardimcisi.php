<?php

namespace App\Support;

use App\Models\DenetimKayidi;
use Illuminate\Support\Facades\Auth;

/**
 * Kritik SaaS aksiyonları için denetim kaydı (sessiz başarısızlık).
 */
class DenetimYardimcisi
{
    public static function kaydet(
        string $olay,
        ?string $konuTipi = null,
        ?int $konuId = null,
        ?int $firmaId = null,
        ?array $eskiVeri = null,
        ?array $yeniVeri = null,
    ): void {
        try {
            DenetimKayidi::query()->create([
                'firma_id' => $firmaId,
                'kullanici_id' => Auth::id(),
                'olay' => $olay,
                'konu_tipi' => $konuTipi,
                'konu_id' => $konuId,
                'eski_veri' => $eskiVeri,
                'yeni_veri' => $yeniVeri,
                'ip_adresi' => request()?->ip(),
                'kullanici_ajan' => request()?->userAgent(),
            ]);
        } catch (\Throwable) {
            // İlk kurulum / tablo yoksa panel akışını bozma
        }
    }
}
