<?php

namespace App\Modules\Odeme\Servisler;

use App\Models\Ecommerce\Odeme;
use App\Models\Ecommerce\Siparis;
use App\Modules\Odeme\Contracts\OdemeProviderInterface;
use App\Modules\Odeme\Support\AbstractOdemeProvider;
use App\Modules\Urun\Servisler\SiparisOdemeServisi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class IyzicoOdemeServisi extends AbstractOdemeProvider implements OdemeProviderInterface
{
    private const DEFAULT_MAX_AMOUNT = 100000.0;

    public function __construct(
        private readonly SiparisOdemeServisi $siparisOdemeServisi,
    ) {}

    public function odemeBaslat(Siparis $siparis, array $ayarlar): array
    {
        $apiKey = (string) ($ayarlar['iyzico_api_key'] ?? '');
        $secretKey = (string) ($ayarlar['iyzico_secret_key'] ?? '');
        $baseUrl = (string) ($ayarlar['iyzico_base_url'] ?? 'https://sandbox-api.iyzipay.com');

        if ($apiKey === '' || $secretKey === '') {
            throw ValidationException::withMessages([
                'odeme' => 'iyzico ödeme sağlayıcısı eksik yapılandırılmış.',
            ]);
        }

        $currency = strtoupper((string) ($siparis->para_birimi ?? 'TRY'));
        $iyzicoCurrency = in_array($currency, ['TL', 'TRY'], true) ? 'TRY' : $currency;
        $genelToplam = round((float) $siparis->genel_toplam, 2);
        $maxTutar = $this->maxTutar($ayarlar);
        if ($maxTutar > 0 && $genelToplam >= $maxTutar) {
            throw ValidationException::withMessages([
                'odeme' => 'iyzico bu sipariş tutarını kabul etmiyor. Kartlı ödeme üst limiti '.number_format($maxTutar, 2, ',', '.').' '.$iyzicoCurrency.' altıdır. Lütfen Havale / EFT gibi farklı bir ödeme yöntemi seçin.',
            ]);
        }

        $provider = Odeme::PROVIDER_IYZICO;

        $bekleyen = Odeme::query()
            ->where('siparis_id', $siparis->id)
            ->where('provider', $provider)
            ->where('durum', Odeme::DURUM_BEKLEMEDE)
            ->orderByDesc('id')
            ->first();

        $odeme = $bekleyen ?: $this->siparisOdemeServisi->providerBekleyenOdemeOlustur($siparis, $provider);
        $providerRef = (string) $odeme->provider_ref;

        $siparis->loadMissing('kalemler');

        [$name, $surname] = $this->adSoyadBol($siparis->musteri_ad_soyad ?? 'User');
        $email = (string) ($siparis->musteri_email ?? 'test@example.com');
        $phone = (string) ($siparis->musteri_telefon ?? '');
        $address = (string) ($siparis->teslimat_adresi ?? '');

        $callbackUrl = (string) ($ayarlar['callback_url'] ?? route('odeme.webhook.callback', ['provider' => Odeme::PROVIDER_IYZICO]));

        // Checkout Form Initialize (CF) - Redirect (Hosted) akış.
        $uriPath = '/payment/iyzipos/checkoutform/initialize/auth/ecom';
        $url = rtrim($baseUrl, '/').$uriPath;

        $basketId = (string) ($siparis->siparis_no ?? (string) $siparis->id);

        $payload = [
            'locale' => 'tr',
            'conversationId' => $providerRef,
            'price' => number_format($genelToplam, 2, '.', ''),
            'paidPrice' => number_format($genelToplam, 2, '.', ''),
            'currency' => $iyzicoCurrency === 'TRY' ? 'TRY' : $iyzicoCurrency,
            'basketId' => $basketId,
            'callbackUrl' => $callbackUrl,
            'enabledInstallments' => [1, 2, 3, 6, 9],
            'buyer' => [
                'id' => (string) $siparis->kullanici_id ?: (string) $siparis->id,
                'name' => $name,
                'surname' => $surname,
                'identityNumber' => '11111111111',
                'email' => $email,
                'gsmNumber' => $phone,
                'registrationAddress' => $address,
                'city' => 'Istanbul',
                'country' => 'TR',
            ],
            'shippingAddress' => [
                'address' => $address,
                'contactName' => $name.' '.$surname,
                'city' => 'Istanbul',
                'country' => 'TR',
            ],
            'billingAddress' => [
                'address' => $address,
                'contactName' => $name.' '.$surname,
                'city' => 'Istanbul',
                'country' => 'TR',
            ],
            'basketItems' => $this->basketItemsHazirla($siparis),
        ];

        $auth = $this->iyzwsV2AuthorizationHeader(
            apiKey: $apiKey,
            secretKey: $secretKey,
            uriPath: $uriPath,
            payload: $payload,
        );

        $res = Http::timeout(20)
            ->withHeaders([
                'Authorization' => $auth['authorization'],
                'x-iyzi-rnd' => $auth['x_iyzi_rnd'],
                'Content-Type' => 'application/json',
            ])
            ->withBody(json_encode($payload, JSON_UNESCAPED_UNICODE), 'application/json')
            ->post($url);

        if (! $res->ok()) {
            throw ValidationException::withMessages([
                'odeme' => 'iyzico ödeme başlatılamadı (iletişim hatası).',
            ]);
        }

        $result = $res->json();
        if (! is_array($result) || ($result['status'] ?? '') !== 'success') {
            $msg = (string) ($result['errorMessage'] ?? $result['message'] ?? 'iyzico başlatma başarısız.');
            throw ValidationException::withMessages([
                'odeme' => 'iyzico başlatma başarısız: '.$msg,
            ]);
        }

        $paymentPageUrl = (string) ($result['paymentPageUrl'] ?? '');
        $checkoutFormContent = (string) ($result['checkoutFormContent'] ?? '');

        if ($paymentPageUrl !== '') {
            return [
                'mode' => 'redirect',
                'url' => $paymentPageUrl,
                'provider_ref' => $providerRef,
                'odeme_id' => (int) $odeme->id,
            ];
        }

        // fallback: form content dönerse onu view'da işlemek için döndürüyoruz.
        if ($checkoutFormContent !== '') {
            return [
                'mode' => 'iyzico_checkout_form',
                'checkout_form_content' => $checkoutFormContent,
                'provider_ref' => $providerRef,
                'odeme_id' => (int) $odeme->id,
            ];
        }

        throw ValidationException::withMessages([
            'odeme' => 'iyzico yanıtı eksik (redirect/form bilgisi yok).',
        ]);
    }

    public function callbackDogrula(Request $request, array $ayarlar): bool
    {
        $secretKey = (string) ($ayarlar['iyzico_secret_key'] ?? '');
        if ($secretKey === '') {
            return false;
        }

        $signatureHeader = (string) ($request->header('X-Iyz-Signature-V3') ?? $request->header('X-IYZ-SIGNATURE-V3') ?? '');
        if ($signatureHeader !== '') {
            // Webhook direct / JSON formu
            $payload = $request->json()->all();
            if (! is_array($payload)) {
                return false;
            }

            $eventType = (string) ($payload['iyziEventType'] ?? '');
            $paymentId = (string) ($payload['paymentId'] ?? $payload['iyziPaymentId'] ?? '');
            $conversationId = (string) ($payload['paymentConversationId'] ?? '');
            $status = (string) ($payload['status'] ?? '');

            if ($eventType === '' || $paymentId === '' || $conversationId === '' || $status === '') {
                return false;
            }

            $message = $secretKey.$eventType.$paymentId.$conversationId.$status;
            $expected = hash_hmac('sha256', $message, $secretKey);

            return hash_equals(strtolower($expected), strtolower($signatureHeader));
        }

        // callbackUrl redirection: signature param'ı var ise bunu doğrula.
        $signature = (string) ($request->input('signature') ?? '');
        if ($signature === '') {
            return false;
        }

        $conversationData = (string) ($request->input('conversationData') ?? '');
        $conversationId = (string) ($request->input('conversationId') ?? '');
        $mdStatus = (string) ($request->input('mdStatus') ?? '');
        $paymentId = (string) ($request->input('paymentId') ?? '');
        $status = (string) ($request->input('status') ?? '');

        if ($conversationData === '' || $conversationId === '' || $mdStatus === '' || $paymentId === '' || $status === '') {
            return false;
        }

        $message = implode(':', [$conversationData, $conversationId, $mdStatus, $paymentId, $status]);
        $expected = hash_hmac('sha256', $message, $secretKey);

        return hash_equals(strtolower($expected), strtolower($signature));
    }

    public function callbackSonucunuCoz(Request $request, array $ayarlar): array
    {
        $payload = $request->json()->all();

        if (is_array($payload) && ! empty($payload)) {
            $conversationId = (string) ($payload['paymentConversationId'] ?? '');
            $status = (string) ($payload['status'] ?? '');

            $providerRef = $conversationId;
            $siparisId = $this->extractSiparisIdFromProviderRef($providerRef) ?? 0;

            return [
                'basarili' => strtoupper($status) === 'SUCCESS',
                'siparis_id' => (int) $siparisId,
                'provider_ref' => $providerRef,
                'meta' => [
                    'iyziEventType' => (string) ($payload['iyziEventType'] ?? ''),
                    'paymentId' => (string) ($payload['paymentId'] ?? ''),
                    'status' => $status,
                    'paid_price' => $payload['paidPrice'] ?? null,
                ],
            ];
        }

        // redirect-based fallback (form-urlencoded)
        $providerRef = (string) ($request->input('conversationId') ?? '');
        $siparisId = $this->extractSiparisIdFromProviderRef($providerRef) ?? 0;

        $status = (string) ($request->input('status') ?? '');

        return [
            'basarili' => strtoupper($status) === 'SUCCESS',
            'siparis_id' => (int) $siparisId,
            'provider_ref' => $providerRef,
            'meta' => [
                'mdStatus' => (string) ($request->input('mdStatus') ?? ''),
                'paid_price' => $request->input('paidPrice'),
            ],
        ];
    }

    private function adSoyadBol(string $adSoyad): array
    {
        $adSoyad = trim($adSoyad);
        if ($adSoyad === '') {
            return ['User', ''];
        }

        $parts = preg_split('/\s+/', $adSoyad) ?: [];
        $parts = array_values(array_filter($parts, fn ($p) => $p !== ''));
        if (count($parts) === 1) {
            return [$parts[0], ''];
        }

        $name = (string) array_shift($parts);
        $surname = (string) implode(' ', $parts);

        return [$name, $surname];
    }

    /**
     * iyzico "price" toplamı, basket item price toplamına birebir eşit olmalı.
     * Siparişte KDV ve kampanya/indirim üst toplamda tutulduğu için satırlara burada dağıtıyoruz.
     *
     * @return array<int, array<string, mixed>>
     */
    private function basketItemsHazirla(Siparis $siparis): array
    {
        $kalemler = $siparis->kalemler->values();
        $araToplam = round((float) $kalemler->sum('satir_toplami'), 2);
        $indirimToplami = round((float) ($siparis->indirim_toplami ?? 0), 2);
        $kdvMatrahi = max(0, round($araToplam - $indirimToplami, 2));

        $items = [];
        $birikenToplam = 0.0;
        $sonIndex = max(0, $kalemler->count() - 1);

        foreach ($kalemler as $index => $kalem) {
            $satirNet = round((float) $kalem->satir_toplami, 2);
            $oran = $araToplam > 0 ? $satirNet / $araToplam : 0.0;
            $dagitilanNet = $index === $sonIndex
                ? round(max(0, $kdvMatrahi - $birikenToplam), 2)
                : round($kdvMatrahi * $oran, 2);

            $satirKdvli = round($dagitilanNet * (1 + (((float) ($kalem->kdv_orani ?? 0)) / 100)), 2);

            $items[] = [
                'id' => (string) $kalem->stok_karti_id,
                'price' => number_format($satirKdvli, 2, '.', ''),
                'name' => (string) $kalem->urun_adi_snapshot,
                'category1' => (string) ($kalem->urun_kodu_snapshot ?: 'GENEL'),
                'itemType' => 'PHYSICAL',
            ];

            $birikenToplam += $dagitilanNet;
        }

        $itemsToplami = round(array_sum(array_map(
            static fn (array $item): float => (float) $item['price'],
            $items
        )), 2);
        $genelToplam = round((float) $siparis->genel_toplam, 2);
        $fark = round($genelToplam - $itemsToplami, 2);

        if (abs($fark) >= 0.01 && isset($items[$sonIndex])) {
            $sonFiyat = round((float) $items[$sonIndex]['price'] + $fark, 2);
            $items[$sonIndex]['price'] = number_format($sonFiyat, 2, '.', '');
        }

        return $items;
    }

    private function maxTutar(array $ayarlar): float
    {
        $maxTutar = (float) ($ayarlar['max_tutar'] ?? $ayarlar['iyzico_max_tutar'] ?? 0);

        return $maxTutar > 0 ? $maxTutar : self::DEFAULT_MAX_AMOUNT;
    }

    /**
     * @return array{authorization:string,x_iyzi_rnd:string}
     */
    private function iyzwsV2AuthorizationHeader(string $apiKey, string $secretKey, string $uriPath, array $payload): array
    {
        // Iyzico auth (HMACSHA256) - resmi şemaya uygun.
        $randomKey = (string) random_int(1000000000, 9999999999);

        $requestBody = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $encryptedData = hash_hmac('sha256', $randomKey.$uriPath.$requestBody, $secretKey);

        $authorizationString = 'apiKey:'.$apiKey
            .'&randomKey:'.$randomKey
            .'&signature:'.$encryptedData;

        $base64EncodedAuthorization = base64_encode($authorizationString);

        return [
            'authorization' => 'IYZWSv2 '.$base64EncodedAuthorization,
            'x_iyzi_rnd' => $randomKey,
        ];
    }
}
