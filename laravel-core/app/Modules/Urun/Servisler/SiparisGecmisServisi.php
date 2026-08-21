<?php

namespace App\Modules\Urun\Servisler;

use App\Models\Ecommerce\Siparis;
use App\Models\Ecommerce\SiparisGecmisi;
use Illuminate\Support\Facades\Auth;

class SiparisGecmisServisi
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function kaydet(
        Siparis $siparis,
        string $olay,
        ?string $aciklama = null,
        ?array $meta = null,
        ?int $kullaniciId = null,
    ): SiparisGecmisi {
        $kid = $kullaniciId ?? (Auth::id() !== null ? (int) Auth::id() : null);

        return SiparisGecmisi::query()->create([
            'siparis_id' => $siparis->id,
            'kullanici_id' => $kid,
            'olay' => $olay,
            'aciklama' => $aciklama,
            'meta' => $meta,
        ]);
    }
}
