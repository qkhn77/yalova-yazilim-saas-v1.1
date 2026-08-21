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

class PaytrOdemeServisi extends AbstractOdemeProvider implements OdemeProviderInterface
{
    public function __construct(
        private readonly SiparisOdemeServisi $siparisOdemeServisi,
    ) {}

    public function odemeBaslat(Siparis $siparis, array $ayarlar): array
    {
        $userIp = (string) ($ayarlar['user_ip'] ?? '127.0.0.1');

        $merchantId = (string) ($ayarlar['paytr_merchant_id'] ?? '');
        $merchantKey = (string) ($ayarlar['paytr_merchant_key'] ?? '');
        $merchantSalt = (string) ($ayarlar['paytr_merchant_salt'] ?? '');

        if ($merchantId === '' || $merchantKey === '' || $merchantSalt === '') {
            throw ValidationException::withMessages([
                'odeme' => 'PayTR ödeme sağlayıcısı eksik yapılandırılmış.',
            ]);
        }

        $testModu = (int) ((bool) ($ayarlar['test_modu'] ?? false) ? 1 : 0);

        $siparis->loadMissing('kalemler');
        $kalemler = $siparis->kalemler ?? collect();

        $currency = strtoupper((string) ($siparis->para_birimi ?? 'TRY'));
        $paytrCurrency = match ($currency) {
            'TL', 'TRY' => 'TL',
            'USD' => 'USD',
            'EUR' => 'EUR',
            'GBP' => 'GBP',
            'RUB' => 'RUB',
            default => 'TL',
        };

        $provider = Odeme::PROVIDER_PAYTR;

        $bekleyen = Odeme::query()
            ->where('siparis_id', $siparis->id)
            ->where('provider', $provider)
            ->where('durum', Odeme::DURUM_BEKLEMEDE)
            ->orderByDesc('id')
            ->first();

        $odeme = $bekleyen ?: $this->siparisOdemeServisi->providerBekleyenOdemeOlustur($siparis, $provider);

        $providerRef = (string) $odeme->provider_ref;
        $merchantOid = $providerRef;

        $email = (string) ($siparis->musteri_email ?? 'test@example.com');
        $userName = (string) ($siparis->musteri_ad_soyad ?? 'User');
        $userAddress = (string) ($siparis->teslimat_adresi ?? '');
        $userPhone = (string) ($siparis->musteri_telefon ?? '');

        // PayTR payment_amount: kuruş bazında integer.
        $paymentAmount = (int) round(((float) $siparis->genel_toplam) * 100);

        $userBasket = base64_encode(json_encode(
            $kalemler->map(function ($kalem): array {
                return [
                    (string) $kalem->urun_adi_snapshot,
                    number_format((float) $kalem->birim_fiyat, 2, '.', ''),
                    (int) $kalem->miktar,
                ];
            })->values()->all(),
            JSON_UNESCAPED_UNICODE,
        ));

        $noInstallment = 0;
        $maxInstallment = 0;
        $timeoutLimit = (int) ($ayarlar['timeout_limit_dk'] ?? 30);
        $debugOn = (int) ($ayarlar['debug_on'] ?? 0);

        // PayTR iFrame token için paytr_token hesaplama (resmi örnekle birebir).
        $hashStr = $merchantId
            .$userIp
            .$merchantOid
            .$email
            .$paymentAmount
            .$userBasket
            .$noInstallment
            .$maxInstallment
            .$paytrCurrency
            .$testModu;

        $paytrToken = base64_encode(hash_hmac(
            'sha256',
            $hashStr.$merchantSalt,
            $merchantKey,
            true
        ));

        $merchantOkUrl = $ayarlar['merchant_ok_url'] ?? route('checkout.success');
        $merchantFailUrl = $ayarlar['merchant_fail_url'] ?? route('odeme.show', $siparis);

        $postVals = [
            'merchant_id' => $merchantId,
            'user_ip' => $userIp,
            'merchant_oid' => $merchantOid,
            'email' => $email,
            'payment_amount' => $paymentAmount,
            'paytr_token' => $paytrToken,
            'user_basket' => $userBasket,
            'debug_on' => $debugOn,
            'no_installment' => $noInstallment,
            'max_installment' => $maxInstallment,
            'user_name' => $userName,
            'user_address' => $userAddress,
            'user_phone' => $userPhone,
            'merchant_ok_url' => $merchantOkUrl,
            'merchant_fail_url' => $merchantFailUrl,
            'timeout_limit' => $timeoutLimit,
            'currency' => $paytrCurrency,
            'test_mode' => $testModu,
        ];

        $res = Http::asForm()->timeout(20)->post('https://www.paytr.com/odeme/api/get-token', $postVals);
        if (! $res->ok()) {
            throw ValidationException::withMessages([
                'odeme' => 'PayTR token alınamadı (iletişim hatası).',
            ]);
        }

        $result = $res->json();
        if (! is_array($result) || ($result['status'] ?? '') !== 'success') {
            $reason = (string) ($result['reason'] ?? 'PayTR token başarısız.');
            throw ValidationException::withMessages([
                'odeme' => 'PayTR token başarısız: '.$reason,
            ]);
        }

        $token = (string) ($result['token'] ?? '');
        if ($token === '') {
            throw ValidationException::withMessages([
                'odeme' => 'PayTR token yanıtı eksik.',
            ]);
        }

        $iframeSrc = 'https://www.paytr.com/odeme/guvenli/'.$token;

        return [
            'mode' => 'paytr_iframe',
            'iframe_src' => $iframeSrc,
            'provider_ref' => $providerRef,
            'odeme_id' => (int) $odeme->id,
        ];
    }

    public function callbackDogrula(Request $request, array $ayarlar): bool
    {
        $merchantKey = (string) ($ayarlar['paytr_merchant_key'] ?? '');
        $merchantSalt = (string) ($ayarlar['paytr_merchant_salt'] ?? '');
        if ($merchantKey === '' || $merchantSalt === '') {
            return false;
        }

        $post = $request->all();

        $hash = (string) ($post['hash'] ?? '');
        $callbackId = (string) ($post['callback_id'] ?? '');
        $merchantOid = (string) ($post['merchant_oid'] ?? '');
        $status = (string) ($post['status'] ?? '');
        $totalAmount = (string) ($post['total_amount'] ?? '');

        if ($hash === '' || $callbackId === '' || $merchantOid === '' || $status === '' || $totalAmount === '') {
            return false;
        }

        // PayTR iFrame callback hash formülü:
        // base64_encode(hash_hmac('sha256', callback_id+merchant_oid+merchant_salt+status+total_amount, merchant_key, true))
        $expected = base64_encode(hash_hmac(
            'sha256',
            $callbackId.$merchantOid.$merchantSalt.$status.$totalAmount,
            $merchantKey,
            true
        ));

        return hash_equals($expected, $hash);
    }

    public function callbackSonucunuCoz(Request $request, array $ayarlar): array
    {
        $post = $request->all();

        $status = (string) ($post['status'] ?? '');
        $merchantOid = (string) ($post['merchant_oid'] ?? '');

        $providerRef = $merchantOid;
        $siparisId = $this->extractSiparisIdFromProviderRef($providerRef);
        if ($siparisId === null) {
            $siparisId = 0;
        }

        return [
            'basarili' => $status === 'success',
            'siparis_id' => $siparisId,
            'provider_ref' => $providerRef,
            'meta' => [
                'provider_status' => $status,
                'callback_id' => (string) ($post['callback_id'] ?? ''),
                'total_amount_kurus' => (int) ($post['total_amount'] ?? 0),
            ],
        ];
    }
}
