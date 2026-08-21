<?php

namespace App\Services;

use App\Models\DenetimKayidi;
use App\Models\Muhasebe\Cari;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class CariDenetimServisi
{
    /** @var array<int, string> */
    private const IZLENEN_ALANLAR = ['para_birimi', 'durum', 'risk_limiti', 'vade_gunu'];

    public function kaydet(
        string $olay,
        Cari $cari,
        ?array $eskiVeri = null,
        ?array $yeniVeri = null
    ): void {
        if (! Schema::hasTable('denetim_kayitlari')) {
            return;
        }

        $kullanici = Auth::user();
        $kullaniciId = $kullanici instanceof User ? (int) $kullanici->getKey() : null;
        $istek = request();

        $eskiVeri = $this->filtrele($eskiVeri);
        $yeniVeri = $this->filtrele($yeniVeri);
        if ($olay === 'cari_karti.guncelle' && $eskiVeri === [] && $yeniVeri === []) {
            return;
        }

        DenetimKayidi::query()->create([
            'firma_id' => $cari->firma_id,
            'kullanici_id' => $kullaniciId,
            'olay' => $olay,
            'konu_tipi' => Cari::class,
            'konu_id' => $cari->getKey(),
            'eski_veri' => $eskiVeri,
            'yeni_veri' => $yeniVeri,
            'ip_adresi' => $istek?->ip(),
            'kullanici_ajan' => $istek ? substr((string) $istek->userAgent(), 0, 65000) : null,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $veri
     * @return array<string, mixed>
     */
    private function filtrele(?array $veri): array
    {
        if (! is_array($veri)) {
            return [];
        }

        $sonuc = [];
        foreach (self::IZLENEN_ALANLAR as $alan) {
            if (array_key_exists($alan, $veri)) {
                $sonuc[$alan] = $veri[$alan];
            }
        }

        return $sonuc;
    }
}
