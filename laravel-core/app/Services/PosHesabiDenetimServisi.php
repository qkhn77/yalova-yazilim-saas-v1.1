<?php

namespace App\Services;

use App\Models\DenetimKayidi;
use App\Models\Muhasebe\PosHesabi;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * POS hesabı CRUD işlemleri için denetim kaydı (audit).
 */
class PosHesabiDenetimServisi
{
    public function kaydet(
        string $olay,
        PosHesabi $pos,
        ?array $eskiVeri = null,
        ?array $yeniVeri = null
    ): void {
        if (! Schema::hasTable('denetim_kayitlari')) {
            return;
        }

        $kullanici = Auth::user();
        $kullaniciId = $kullanici instanceof User ? (int) $kullanici->getKey() : null;
        $istek = request();

        DenetimKayidi::query()->create([
            'firma_id' => $pos->firma_id,
            'kullanici_id' => $kullaniciId,
            'olay' => $olay,
            'konu_tipi' => PosHesabi::class,
            'konu_id' => $pos->getKey(),
            'eski_veri' => $eskiVeri,
            'yeni_veri' => $yeniVeri,
            'ip_adresi' => $istek?->ip(),
            'kullanici_ajan' => $istek ? substr((string) $istek->userAgent(), 0, 65000) : null,
            'created_at' => now(),
        ]);
    }
}
