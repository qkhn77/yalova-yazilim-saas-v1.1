<?php

namespace App\Http\Controllers;

use App\Models\Ecommerce\Odeme;
use App\Models\Ecommerce\Siparis;
use App\Modules\Odeme\OdemeProviderFactory;
use App\Modules\Urun\Servisler\SiparisOdemeServisi;
use App\Services\EcommerceOdemeFirmaAyarServisi;
use App\Services\SistemOlayServisi;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * PSP webhook / callback giriş noktası.
 */
class OdemeWebhookDenetleyici extends Controller
{
    public function __construct(
        private readonly OdemeProviderFactory $odemeProviderFactory,
        private readonly EcommerceOdemeFirmaAyarServisi $ecommerceOdemeFirmaAyarServisi,
        private readonly SiparisOdemeServisi $siparisOdemeServisi,
        private readonly SistemOlayServisi $sistemOlayServisi,
    ) {}

    public function isle(Request $request, string $provider): Response
    {
        $provider = strtolower(trim($provider));
        if (! in_array($provider, [Odeme::PROVIDER_PAYTR, Odeme::PROVIDER_IYZICO], true)) {
            $this->log('odeme.callback.hata', ['provider' => $provider, 'sebep' => 'desteklenmeyen_provider'], 'warning');

            return response('Bad Request: unsupported provider', 400);
        }

        try {
            $providerService = $this->odemeProviderFactory->make($provider);
            $parsed = $providerService->callbackSonucunuCoz($request, []);
            $siparisId = (int) ($parsed['siparis_id'] ?? 0);
            $providerRef = (string) ($parsed['provider_ref'] ?? '');
            $meta = (array) ($parsed['meta'] ?? []);

            $this->log('odeme.callback.geldi', [
                'provider' => $provider,
                'siparis_id' => $siparisId > 0 ? $siparisId : null,
                'provider_ref' => $providerRef,
            ]);

            if ($providerRef === '') {
                $this->log('odeme.callback.hata', ['provider' => $provider, 'sebep' => 'provider_ref_bos'], 'warning');

                return response('Bad Request: invalid provider payload', 400);
            }

            // Önce provider_ref ile ödeme bulunur (en güvenli idempotency anahtarı).
            $odeme = Odeme::query()
                ->where('provider', $provider)
                ->where('provider_ref', $providerRef)
                ->orderByDesc('id')
                ->first();

            if (! $odeme) {
                $this->log('odeme.callback.atlandi', [
                    'provider' => $provider,
                    'provider_ref' => $providerRef,
                    'sebep' => 'provider_ref_bilinmiyor',
                ], 'warning');

                return response('Conflict: provider_ref bilinmiyor', 409);
            }

            if ($siparisId <= 0) {
                $siparisId = (int) $odeme->siparis_id;
            }

            $siparis = Siparis::query()->find($siparisId);
            if (! $siparis) {
                $this->log('odeme.callback.hata', [
                    'provider' => $provider,
                    'provider_ref' => $providerRef,
                    'siparis_id' => $siparisId,
                    'sebep' => 'siparis_bulunamadi',
                ], 'warning');

                return response('Not Found: siparis bulunamadı', 404);
            }

            $firmaId = (int) $siparis->firma_id;
            if ($firmaId <= 0) {
                $this->log('odeme.callback.hata', [
                    'provider' => $provider,
                    'provider_ref' => $providerRef,
                    'siparis_id' => $siparisId,
                    'sebep' => 'firma_id_eksik',
                ], 'warning');

                return response('Bad Request: firma_id eksik', 400);
            }

            $ayarlar = $this->ecommerceOdemeFirmaAyarServisi->odemeAyarlariniGetir($firmaId, $provider);
            if (! $providerService->callbackDogrula($request, $ayarlar)) {
                $this->log('odeme.callback.atlandi', [
                    'provider' => $provider,
                    'provider_ref' => $providerRef,
                    'siparis_id' => $siparisId,
                    'sebep' => 'signature_gecersiz',
                ], 'warning');

                return response('Forbidden: signature doğrulaması başarısız', 403);
            }

            if ($provider === Odeme::PROVIDER_PAYTR) {
                $callbackKurus = (int) ($meta['total_amount_kurus'] ?? 0);
                $beklenenKurus = (int) round(((float) $odeme->tutar) * 100);
                if ($callbackKurus > 0 && $callbackKurus !== $beklenenKurus) {
                    $this->log('odeme.callback.atlandi', [
                        'provider' => $provider,
                        'provider_ref' => $providerRef,
                        'siparis_id' => $siparisId,
                        'sebep' => 'tutar_uyusmazligi',
                    ], 'warning');

                    return response('Unprocessable Entity: tutar uyuşmazlığı', 422);
                }
            }

            if ($provider === Odeme::PROVIDER_IYZICO) {
                $paidPrice = $meta['paid_price'] ?? null;
                if (is_numeric($paidPrice)) {
                    $callbackKurus = (int) round(((float) $paidPrice) * 100);
                    $beklenenKurus = (int) round(((float) $odeme->tutar) * 100);
                    if ($callbackKurus !== $beklenenKurus) {
                        $this->log('odeme.callback.atlandi', [
                            'provider' => $provider,
                            'provider_ref' => $providerRef,
                            'siparis_id' => $siparisId,
                            'sebep' => 'tutar_uyusmazligi',
                        ], 'warning');

                        return response('Unprocessable Entity: tutar uyuşmazlığı', 422);
                    }
                }
            }

            $eventHash = hash('sha256', (string) $request->getContent());
            $eventKey = sprintf('odeme_webhook_event:%s:%s:%s', $provider, $providerRef, $eventHash);
            if (! Cache::add($eventKey, now()->toIso8601String(), now()->addHours(6))) {
                $this->log('odeme.callback.atlandi', [
                    'provider' => $provider,
                    'provider_ref' => $providerRef,
                    'siparis_id' => $siparisId,
                    'sebep' => 'yinelenen_event',
                ]);

                return response('OK', 200)->header('Content-Type', 'text/plain');
            }

            // Uygulama katmanında da idempotent; burada erken-atla ile gürültü azaltılır.
            if ((bool) ($parsed['basarili'] ?? false)
                && Siparis::odemeAlindiDurumMu($siparis->durum)
                && (bool) $siparis->stok_dusuldu_mi
                && $odeme->durum === Odeme::DURUM_BASARILI) {
                $this->log('odeme.callback.atlandi', [
                    'provider' => $provider,
                    'provider_ref' => $providerRef,
                    'siparis_id' => $siparisId,
                    'sebep' => 'zaten_islenmis_basarili',
                ]);

                return response('OK', 200)->header('Content-Type', 'text/plain');
            }

            if ((bool) ($parsed['basarili'] ?? false)) {
                $this->siparisOdemeServisi->providerOdemeCallbackBasarili(
                    $provider,
                    $providerRef,
                    $siparisId,
                    $meta,
                );
            } else {
                $this->siparisOdemeServisi->providerOdemeCallbackBasarisiz(
                    $provider,
                    $providerRef,
                    $siparisId,
                    $meta,
                );
            }

            $this->log('odeme.callback.islendi', [
                'provider' => $provider,
                'provider_ref' => $providerRef,
                'siparis_id' => $siparisId,
                'basarili' => (bool) ($parsed['basarili'] ?? false),
            ]);

            return response('OK', 200)->header('Content-Type', 'text/plain');
        } catch (Throwable $e) {
            $this->log('odeme.callback.hata', [
                'provider' => $provider,
                'hata' => $e->getMessage(),
            ], 'error');

            return response('Internal Server Error', 500);
        }
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function log(string $message, array $context, string $level = 'info'): void
    {
        Log::channel('stack')->{$level}($message, $context);
        $this->sistemOlayServisi->olayKaydet($message, $level, $message, $context);
    }
}
