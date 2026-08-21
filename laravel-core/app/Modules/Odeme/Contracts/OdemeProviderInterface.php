<?php

namespace App\Modules\Odeme\Contracts;

use App\Models\Ecommerce\Siparis;
use Illuminate\Http\Request;

interface OdemeProviderInterface
{
    /**
     * @param  array<string, mixed>  $ayarlar
     * @return array<string, mixed>
     */
    public function odemeBaslat(Siparis $siparis, array $ayarlar): array;

    /**
     * Provider callback güvenlik doğrulaması.
     *
     * @param  array<string, mixed>  $ayarlar
     */
    public function callbackDogrula(Request $request, array $ayarlar): bool;

    /**
     * Callback'ten sonucu çözer (idempotency için provider_ref ile eşleşme).
     *
     * @param  array<string, mixed>  $ayarlar
     * @return array{
     *  basarili: bool,
     *  siparis_id: int,
     *  provider_ref: string
     * }
     */
    public function callbackSonucunuCoz(Request $request, array $ayarlar): array;
}
