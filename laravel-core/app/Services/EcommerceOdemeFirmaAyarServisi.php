<?php

namespace App\Services;

use App\Models\FirmaAyari;
use App\Providers\Filament\AdminPanelProvider;
use App\Support\DenetimYardimcisi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class EcommerceOdemeFirmaAyarServisi
{
    public function __construct(
        private readonly FirmaAyarDeposu $depo,
        private readonly SistemOlayServisi $sistemOlayServisi,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function kaydetAyarlar(int $firmaId, array $data): void
    {
        if ($firmaId <= 0) {
            return;
        }

        $deger = fn (string $key, mixed $default = null): mixed => $data[$key] ?? $default;

        $aktif = (bool) $deger('ecommerce_odeme_aktif_mi', false);
        $provider = (string) ($deger('ecommerce_odeme_provider', 'paytr') ?? 'paytr');
        if (! in_array($provider, ['paytr', 'iyzico'], true)) {
            $provider = 'paytr';
        }

        $oncekiProvider = (string) ($this->depo->oku($firmaId, 'ecommerce_odeme_provider', '') ?? '');
        $oncekiAktif = (bool) $this->depo->oku($firmaId, 'ecommerce_odeme_aktif_mi', false);
        $oncekiTestModu = (bool) $this->depo->oku($firmaId, 'test_modu', false);

        $this->depo->yaz($firmaId, 'ecommerce_odeme_aktif_mi', $aktif);
        $this->depo->yaz($firmaId, 'ecommerce_odeme_provider', $provider);

        $this->depo->yaz($firmaId, 'test_modu', (bool) $deger('test_modu', false));
        $this->depo->yaz($firmaId, 'odeme_aciklama_sablonu', (string) $deger('odeme_aciklama_sablonu', ''));

        // PayTR
        $this->depo->yaz($firmaId, 'paytr_merchant_id', $deger('paytr_merchant_id', null));
        if (array_key_exists('paytr_merchant_key', $data)) {
            $this->depo->yaz($firmaId, 'paytr_merchant_key', $deger('paytr_merchant_key', null));
        }
        if (array_key_exists('paytr_merchant_salt', $data)) {
            $this->depo->yaz($firmaId, 'paytr_merchant_salt', $deger('paytr_merchant_salt', null));
        }

        // iyzico
        if (array_key_exists('iyzico_api_key', $data)) {
            $this->depo->yaz($firmaId, 'iyzico_api_key', $deger('iyzico_api_key', null));
        }
        if (array_key_exists('iyzico_secret_key', $data)) {
            $this->depo->yaz($firmaId, 'iyzico_secret_key', $deger('iyzico_secret_key', null));
        }
        $this->depo->yaz($firmaId, 'iyzico_base_url', $deger('iyzico_base_url', 'https://sandbox-api.iyzipay.com'));

        // İstersen firma "özelleşmiş callback url" de set edebilir; yoksa sistem route'u kullanır.
        $this->depo->yaz($firmaId, 'callback_url', $deger('callback_url', null));

        $this->sistemOlayServisi->olayKaydet('ayar.odeme_degisti', 'info', 'Firma odeme ayarlari guncellendi.', [
            'firma_id' => $firmaId,
            'kullanici_id' => Auth::id(),
            'provider_onceki' => $oncekiProvider,
            'provider_yeni' => $provider,
            'secret_guncellendi' => [
                'paytr_merchant_key' => array_key_exists('paytr_merchant_key', $data),
                'paytr_merchant_salt' => array_key_exists('paytr_merchant_salt', $data),
                'iyzico_api_key' => array_key_exists('iyzico_api_key', $data),
                'iyzico_secret_key' => array_key_exists('iyzico_secret_key', $data),
            ],
        ]);

        DenetimYardimcisi::kaydet(
            olay: 'odeme_ayari.guncelle',
            konuTipi: FirmaAyari::class,
            konuId: null,
            firmaId: $firmaId,
            eskiVeri: [
                'provider' => $oncekiProvider,
                'aktif' => $oncekiAktif,
                'test_modu' => $oncekiTestModu,
            ],
            yeniVeri: [
                'provider' => $provider,
                'aktif' => $aktif,
                'test_modu' => (bool) $deger('test_modu', false),
                'secret_degisti' => [
                    'paytr_merchant_key' => array_key_exists('paytr_merchant_key', $data),
                    'paytr_merchant_salt' => array_key_exists('paytr_merchant_salt', $data),
                    'iyzico_api_key' => array_key_exists('iyzico_api_key', $data),
                    'iyzico_secret_key' => array_key_exists('iyzico_secret_key', $data),
                ],
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function odemeAyarlariniGetir(int $firmaId, ?string $overrideProvider = null): array
    {
        $aktif = (bool) $this->depo->oku($firmaId, 'ecommerce_odeme_aktif_mi', false);
        $provider = $overrideProvider ?: (string) $this->depo->oku($firmaId, 'ecommerce_odeme_provider', '');

        $testModu = (bool) $this->depo->oku($firmaId, 'test_modu', false);
        $odemeAciklamaSablonu = (string) $this->depo->oku($firmaId, 'odeme_aciklama_sablonu', '');

        $callbackUrl = (string) ($this->depo->oku($firmaId, 'callback_url', '') ?? '');
        if ($callbackUrl === '') {
            if ($provider === '') {
                $callbackUrl = '';
            } else {
                $callbackUrl = route('odeme.webhook.callback', ['provider' => $provider]);
            }
        }

        return [
            'ecommerce_odeme_aktif_mi' => $aktif,
            'ecommerce_odeme_provider' => $provider,
            'test_modu' => $testModu,
            'odeme_aciklama_sablonu' => $odemeAciklamaSablonu,
            'callback_url' => $callbackUrl,

            'paytr_merchant_id' => $this->depo->oku($firmaId, 'paytr_merchant_id', null),
            'paytr_merchant_key' => $this->depo->oku($firmaId, 'paytr_merchant_key', null),
            'paytr_merchant_salt' => $this->depo->oku($firmaId, 'paytr_merchant_salt', null),

            'iyzico_api_key' => $this->depo->oku($firmaId, 'iyzico_api_key', null),
            'iyzico_secret_key' => $this->depo->oku($firmaId, 'iyzico_secret_key', null),
            'iyzico_base_url' => $this->depo->oku($firmaId, 'iyzico_base_url', 'https://sandbox-api.iyzipay.com'),
        ];
    }

    /**
     * Kullanıcıya dönük mesajla ayar kontrolü.
     *
     * @return array<string, mixed>|null
     */
    public function kontrolOdemeBaslatmaAyarlarVeyaNull(int $firmaId): ?array
    {
        $ayarlar = $this->odemeAyarlariniGetir($firmaId);
        if (! (bool) ($ayarlar['ecommerce_odeme_aktif_mi'] ?? false)) {
            return null;
        }

        $provider = (string) ($ayarlar['ecommerce_odeme_provider'] ?? '');
        if ($provider === '') {
            throw ValidationException::withMessages([
                'odeme' => 'Ödeme sağlayıcısı seçilmemiş. Firma ayarlarından ödeme provider’ını seçin: '.url('/'.AdminPanelProvider::adminPath().'/firma-ayarlari'),
            ]);
        }
        if (! in_array($provider, ['paytr', 'iyzico'], true)) {
            throw ValidationException::withMessages([
                'odeme' => 'Desteklenmeyen ödeme sağlayıcısı seçilmiş. Lütfen firma ayarlarından provider seçimini düzeltin: '.url('/'.AdminPanelProvider::adminPath().'/firma-ayarlari'),
            ]);
        }

        $link = url('/'.AdminPanelProvider::adminPath().'/firma-ayarlari');

        if ($provider === 'paytr') {
            foreach (['paytr_merchant_id', 'paytr_merchant_key', 'paytr_merchant_salt'] as $key) {
                if (! filled($ayarlar[$key] ?? null)) {
                    throw ValidationException::withMessages([
                        'odeme' => 'PayTR ayarları eksik. Lütfen firma ayarlarından Merchant bilgilerini girin: '.$link,
                    ]);
                }
            }
        }

        if ($provider === 'iyzico') {
            foreach (['iyzico_api_key', 'iyzico_secret_key'] as $key) {
                if (! filled($ayarlar[$key] ?? null)) {
                    throw ValidationException::withMessages([
                        'odeme' => 'iyzico ayarları eksik. Lütfen firma ayarlarından API anahtarlarını girin: '.$link,
                    ]);
                }
            }

            // base_url opsiyonel; default sandbox olacak.
            if (! filled($ayarlar['iyzico_base_url'] ?? '')) {
                $ayarlar['iyzico_base_url'] = 'https://sandbox-api.iyzipay.com';
            }
        }

        if (! filled($ayarlar['callback_url'] ?? null)) {
            $ayarlar['callback_url'] = route('odeme.webhook.callback', ['provider' => $provider]);
        }

        return $ayarlar;
    }
}
