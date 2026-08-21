<?php

namespace App\Http\Controllers;

use App\Services\EcommerceOdemeZamanAsimiFallbackServisi;
use App\Services\SistemOlayServisi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EcommerceCronFallbackController extends Controller
{
    public function __construct(
        private readonly SistemOlayServisi $sistemOlayServisi,
    ) {}

    public function odemeZamanAsimi(Request $request, EcommerceOdemeZamanAsimiFallbackServisi $servisi): JsonResponse
    {
        $token = (string) $request->query('token', '');
        $expected = (string) config('ecommerce.cron_fallback_token', '');

        if ($expected === '') {
            $this->sistemOlayServisi->olayKaydet('cron.fallback.token_eksik', 'critical', 'Cron fallback token konfiguru bos.', [
                'ip' => (string) $request->ip(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'Service Unavailable',
            ], 503);
        }

        if (! hash_equals($expected, $token)) {
            return response()->json([
                'ok' => false,
                'error' => 'Forbidden',
            ], 403);
        }

        $firmaId = $request->query('firma_id');
        $firmaId = is_numeric($firmaId) && (int) $firmaId > 0 ? (int) $firmaId : null;

        Log::info('E-ticaret: cron endpoint çağrıldı', [
            'firma_id' => $firmaId,
            'ip' => (string) $request->ip(),
        ]);

        $sonuc = $servisi->tetikle($firmaId, force: false);

        return response()->json([
            'ok' => true,
            'result' => $sonuc,
        ]);
    }
}
