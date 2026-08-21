<?php

namespace Tests\Feature\Urun;

use App\Models\Ecommerce\Odeme;
use App\Models\Ecommerce\Siparis;
use App\Models\Ecommerce\SiparisGecmisi;
use App\Models\Firma;
use App\Models\FirmaModulu;
use App\Models\Modul;
use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokKategorisi;
use App\Modules\Odeme\OdemeProviderFactory;
use App\Modules\Odeme\Servisler\IyzicoOdemeServisi;
use App\Modules\Odeme\Servisler\PaytrOdemeServisi;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Services\EcommerceFirmaAyarServisi;
use App\Services\FirmaAyarDeposu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Feature\Urun\Concerns\CheckoutTestVerileri;

#[\PHPUnit\Framework\Attributes\Group('unpublished-web')]
class OdemeProviderIntegrationTest extends TestCase
{
    use CheckoutTestVerileri;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
        config(['ecommerce.odeme_dakika' => 15]);
    }

    private function firmaOlustur(string $ad): Firma
    {
        $firma = Firma::query()->create([
            'ad' => $ad,
            'kisa_ad' => substr($ad, 0, 2).'-'.uniqid(),
            'firma_kodu' => 'F-'.$ad.'-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);

        $this->ecommerceModulAktifEt($firma);

        return $firma;
    }

    private function ecommerceModulAktifEt(Firma $firma): void
    {
        $modul = Modul::query()->firstOrCreate(
            ['kod' => 'e_ticaret'],
            ['ad' => 'E-ticaret', 'aktif_mi' => true, 'siralama' => 50],
        );
        if (! $modul->aktif_mi) {
            $modul->update(['aktif_mi' => true]);
        }

        FirmaModulu::query()->updateOrCreate(
            ['firma_id' => $firma->id, 'modul_id' => $modul->id],
            ['durum' => 'aktif'],
        );

        $cari = Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'EC-C-'.uniqid(),
            'ad' => 'E-ticaret Cari',
            'tur' => 'musteri',
            'durum' => 'aktif',
            'para_birimi' => 'TRY',
        ]);
        $kasa = KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'EC-K-'.uniqid(),
            'ad' => 'E-ticaret Kasa',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);

        app(EcommerceFirmaAyarServisi::class)->kaydetAyarlar((int) $firma->id, [
            'ecommerce_etkin_mi' => true,
            'ecommerce_tahsilat_cari_id' => $cari->id,
            'ecommerce_tahsilat_kasa_id' => $kasa->id,
            'ecommerce_otomatik_genel_kasa_kullan' => false,
            'ecommerce_cron_fallback_etkin_mi' => true,
        ]);

        $this->checkoutTestVarsayilanlariniOlustur($firma);
    }

    private function urunHazirla(Firma $firma, float $stok = 10): StokKarti
    {
        $kategori = StokKategorisi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'KTG',
            'ad' => 'Kategori',
            'aktif_mi' => true,
            'is_sabit' => false,
        ]);
        $birim = Birim::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'AD',
            'ad' => 'Adet',
            'aktif_mi' => true,
            'is_sabit' => false,
        ]);

        return StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'STK-OD',
            'ad' => 'Ödeme Test Ürün',
            'slug' => 'odeme-test-urun-'.uniqid(),
            'tur' => StokKartiTuru::ETicaret->value,
            'durum' => 'aktif',
            'kategori_id' => $kategori->id,
            'kategori_kodu' => $kategori->kod,
            'birim' => $birim->kod,
            'satis_fiyati' => 50,
            'kdv_orani' => 0,
            'stok_takip' => true,
            'stok_miktari' => $stok,
            'rezerve_miktar' => 0,
        ]);
    }

    private function checkoutVerisi(Firma|int|null $firma = null): array
    {
        return $this->checkoutTestVerisi($firma);
    }

    private function cariVeKasaOlustur(Firma $firma): array
    {
        $cari = Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'C-'.uniqid(),
            'ad' => 'E-ticaret',
            'tur' => 'musteri',
            'durum' => 'aktif',
            'para_birimi' => 'TRY',
        ]);
        $kasa = KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'K-'.uniqid(),
            'ad' => 'Web kasa',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);

        return [$cari, $kasa];
    }

    private function siparisOlustur(StokKarti $urun, float $miktar = 1): Siparis
    {
        \App\Models\Ecommerce\SepetKalemi::query()->delete();
        \App\Models\Ecommerce\Sepet::query()->delete();
        $this->flushSession();

        $this->post(route('cart.add', $urun->slug), ['miktar' => $miktar])
            ->assertSessionHasNoErrors();
        $this->post(route('checkout.store'), $this->checkoutVerisi((int) $urun->firma_id))
            ->assertSessionHasNoErrors();

        return Siparis::query()->orderByDesc('id')->firstOrFail();
    }

    private function odemeAyarlariniYazPaytr(Firma $firma, string $merchantId, string $merchantKey, string $merchantSalt): void
    {
        $depo = app(FirmaAyarDeposu::class);
        $depo->yaz($firma->id, 'ecommerce_odeme_aktif_mi', true);
        $depo->yaz($firma->id, 'ecommerce_odeme_provider', Odeme::PROVIDER_PAYTR);
        $depo->yaz($firma->id, 'test_modu', false);
        $depo->yaz($firma->id, 'paytr_merchant_id', $merchantId);
        $depo->yaz($firma->id, 'paytr_merchant_key', $merchantKey);
        $depo->yaz($firma->id, 'paytr_merchant_salt', $merchantSalt);
    }

    private function odemeAyarlariniYazIyzico(Firma $firma, string $apiKey, string $secretKey, string $baseUrl): void
    {
        $depo = app(FirmaAyarDeposu::class);
        $depo->yaz($firma->id, 'ecommerce_odeme_aktif_mi', true);
        $depo->yaz($firma->id, 'ecommerce_odeme_provider', Odeme::PROVIDER_IYZICO);
        $depo->yaz($firma->id, 'test_modu', false);
        $depo->yaz($firma->id, 'iyzico_api_key', $apiKey);
        $depo->yaz($firma->id, 'iyzico_secret_key', $secretKey);
        $depo->yaz($firma->id, 'iyzico_base_url', $baseUrl);
    }

    private function paytrHash(string $merchantKey, string $merchantSalt, string $callbackId, string $merchantOid, string $status, string $totalAmount): string
    {
        return base64_encode(hash_hmac(
            'sha256',
            $callbackId.$merchantOid.$merchantSalt.$status.$totalAmount,
            $merchantKey,
            true
        ));
    }

    public function test_multi_tenant_provider_secimi_paytr_ve_iyzico(): void
    {
        $firmaPaytr = $this->firmaOlustur('Firma PayTR');
        $firmaIyzico = $this->firmaOlustur('Firma Iyzico');

        $urunPaytr = $this->urunHazirla($firmaPaytr, 10);
        $urunIyzico = $this->urunHazirla($firmaIyzico, 10);

        $this->odemeAyarlariniYazPaytr($firmaPaytr, '123456', 'paytr_key_1', 'paytr_salt_1');
        $this->odemeAyarlariniYazIyzico($firmaIyzico, 'iyz_api_1', 'iyz_secret_1', 'https://sandbox-api.iyzipay.com');

        // Finans ayarı (callback idempotency için değil, sadece servis çalışsın diye).
        [$cari, $kasa] = $this->cariVeKasaOlustur($firmaPaytr);
        config(['ecommerce.tahsilat_cari_id' => $cari->id, 'ecommerce.tahsilat_kasa_id' => $kasa->id]);

        Http::fake(function ($request) {
            $url = (string) $request->url();
            if (str_contains($url, '/odeme/api/get-token')) {
                return Http::response(['status' => 'success', 'token' => 'PAYTR_TOKEN'], 200);
            }
            if (str_contains($url, '/payment/iyzipos/checkoutform/initialize/auth/ecom')) {
                return Http::response([
                    'status' => 'success',
                    'paymentPageUrl' => 'https://iyzico.test/checkout',
                ], 200);
            }

            return Http::response(['status' => 'success'], 200);
        });

        $siparisPaytr = $this->siparisOlustur($urunPaytr, 1);
        $this->get(route('odeme.show', $siparisPaytr))
            ->assertStatus(200)
            ->assertSee('PAYTR_TOKEN');

        $siparisIyzico = $this->siparisOlustur($urunIyzico, 1);
        $this->get(route('odeme.show', $siparisIyzico))
            ->assertRedirect('https://iyzico.test/checkout');
    }

    public function test_eksik_odeme_ayarinda_kullanici_dostu_mesaj(): void
    {
        $firma = $this->firmaOlustur('Firma Eksik');
        $urun = $this->urunHazirla($firma, 10);

        // Aktif ama credential eksik.
        $depo = app(FirmaAyarDeposu::class);
        $depo->yaz($firma->id, 'ecommerce_odeme_aktif_mi', true);
        $depo->yaz($firma->id, 'ecommerce_odeme_provider', Odeme::PROVIDER_PAYTR);
        $depo->yaz($firma->id, 'test_modu', false);

        $siparis = $this->siparisOlustur($urun, 1);

        Http::fake();

        $this->get(route('odeme.show', $siparis))
            ->assertStatus(200)
            ->assertSee('PayTR ayarları eksik');
    }

    public function test_paytr_callback_basarili_ve_idempotent(): void
    {
        config([
            'ecommerce.tahsilat_cari_id' => null,
            'ecommerce.tahsilat_kasa_id' => null,
        ]);

        $firma = $this->firmaOlustur('Firma PayTR CB');
        $urun = $this->urunHazirla($firma, 10);

        [$cari, $kasa] = $this->cariVeKasaOlustur($firma);
        config([
            'ecommerce.tahsilat_cari_id' => $cari->id,
            'ecommerce.tahsilat_kasa_id' => $kasa->id,
        ]);

        $merchantId = '123456';
        $merchantKey = 'paytr_key_2';
        $merchantSalt = 'paytr_salt_2';
        $this->odemeAyarlariniYazPaytr($firma, $merchantId, $merchantKey, $merchantSalt);

        Http::fake(function ($request) {
            if (str_contains((string) $request->url(), '/odeme/api/get-token')) {
                return Http::response(['status' => 'success', 'token' => 'PAYTR_TOKEN_2'], 200);
            }

            return Http::response(['status' => 'success'], 200);
        });

        $siparis = $this->siparisOlustur($urun, 1);
        $this->get(route('odeme.show', $siparis))->assertStatus(200);

        $odeme = Odeme::query()->where('siparis_id', $siparis->id)->where('provider', Odeme::PROVIDER_PAYTR)->firstOrFail();
        $providerRef = (string) $odeme->provider_ref;

        $callbackId = 'CB_1';
        $status = 'success';
        $totalAmount = (string) ((int) round(((float) $siparis->genel_toplam) * 100));
        $hash = $this->paytrHash($merchantKey, $merchantSalt, $callbackId, $providerRef, $status, $totalAmount);

        $this->post(route('odeme.webhook.callback', ['provider' => 'paytr']), [
            'hash' => $hash,
            'callback_id' => $callbackId,
            'merchant_oid' => $providerRef,
            'status' => $status,
            'total_amount' => $totalAmount,
        ])->assertStatus(200)->assertSeeText('OK');

        $siparis->refresh();
        $this->assertSame(Siparis::DURUM_ONAYLANDI_YENI, $siparis->durum);

        $logCount = SiparisGecmisi::query()->where('siparis_id', $siparis->id)->where('olay', SiparisGecmisi::OLAY_ODEME_BASARILI)->count();
        $this->assertSame(1, $logCount);

        $finansCount = FinansHareketi::query()->withoutGlobalScopes()
            ->where('firma_id', $firma->id)
            ->where('referans_turu', Siparis::REFERANS_TURU_FINANS)
            ->where('referans_id', $siparis->id)
            ->count();
        $this->assertSame(1, $finansCount);

        // Replay attack simülasyonu: aynı callback 5 kez tekrar.
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('odeme.webhook.callback', ['provider' => 'paytr']), [
                'hash' => $hash,
                'callback_id' => $callbackId,
                'merchant_oid' => $providerRef,
                'status' => $status,
                'total_amount' => $totalAmount,
            ])->assertStatus(200)->assertSeeText('OK');
        }

        $logCount2 = SiparisGecmisi::query()->where('siparis_id', $siparis->id)->where('olay', SiparisGecmisi::OLAY_ODEME_BASARILI)->count();
        $this->assertSame(1, $logCount2);
    }

    public function test_paytr_callback_hatalide_reddedilir(): void
    {
        $firma = $this->firmaOlustur('Firma PayTR CB Fail');
        $urun = $this->urunHazirla($firma, 10);

        $this->odemeAyarlariniYazPaytr($firma, '123456', 'paytr_key_3', 'paytr_salt_3');

        Http::fake(function ($request) {
            if (str_contains((string) $request->url(), '/odeme/api/get-token')) {
                return Http::response(['status' => 'success', 'token' => 'PAYTR_TOKEN_3'], 200);
            }

            return Http::response(['status' => 'success'], 200);
        });

        $siparis = $this->siparisOlustur($urun, 1);
        $this->get(route('odeme.show', $siparis))->assertStatus(200);

        $odeme = Odeme::query()->where('siparis_id', $siparis->id)->where('provider', Odeme::PROVIDER_PAYTR)->firstOrFail();

        $this->post(route('odeme.webhook.callback', ['provider' => 'paytr']), [
            'hash' => 'BAD_HASH',
            'callback_id' => 'CB_1',
            'merchant_oid' => (string) $odeme->provider_ref,
            'status' => 'success',
            'total_amount' => (string) ((int) round(((float) $siparis->genel_toplam) * 100)),
        ])->assertStatus(403);

        $siparis->refresh();
        $this->assertSame(Siparis::DURUM_ONAY_BEKLIYOR, $siparis->durum);
    }

    public function test_paytr_callback_bilinmeyen_provider_ref_reddedilir(): void
    {
        $firma = $this->firmaOlustur('Firma PayTR Unknown Ref');
        $urun = $this->urunHazirla($firma, 10);

        $merchantKey = 'paytr_key_unk';
        $merchantSalt = 'paytr_salt_unk';
        $this->odemeAyarlariniYazPaytr($firma, '123456', $merchantKey, $merchantSalt);

        Http::fake(function ($request) {
            if (str_contains((string) $request->url(), '/odeme/api/get-token')) {
                return Http::response(['status' => 'success', 'token' => 'PAYTR_TOKEN_4'], 200);
            }

            return Http::response(['status' => 'success'], 200);
        });

        $siparis = $this->siparisOlustur($urun, 1);
        $this->get(route('odeme.show', $siparis))->assertStatus(200);

        $fakeProviderRef = $siparis->id.'-FAKEFAKEFAKEFAKEFAKEFAKE';
        $callbackId = 'CB_UNK';
        $status = 'success';
        $totalAmount = (string) ((int) round(((float) $siparis->genel_toplam) * 100));
        $hash = $this->paytrHash($merchantKey, $merchantSalt, $callbackId, $fakeProviderRef, $status, $totalAmount);

        $this->post(route('odeme.webhook.callback', ['provider' => 'paytr']), [
            'hash' => $hash,
            'callback_id' => $callbackId,
            'merchant_oid' => $fakeProviderRef,
            'status' => $status,
            'total_amount' => $totalAmount,
        ])->assertStatus(409);

        $siparis->refresh();
        $this->assertSame(Siparis::DURUM_ONAY_BEKLIYOR, $siparis->durum);
    }

    public function test_paytr_callback_tutar_uyusmazliginda_reddedilir(): void
    {
        $firma = $this->firmaOlustur('Firma PayTR Amount Mismatch');
        $urun = $this->urunHazirla($firma, 10);

        $merchantKey = 'paytr_key_amt';
        $merchantSalt = 'paytr_salt_amt';
        $this->odemeAyarlariniYazPaytr($firma, '123456', $merchantKey, $merchantSalt);

        Http::fake(function ($request) {
            if (str_contains((string) $request->url(), '/odeme/api/get-token')) {
                return Http::response(['status' => 'success', 'token' => 'PAYTR_TOKEN_5'], 200);
            }

            return Http::response(['status' => 'success'], 200);
        });

        $siparis = $this->siparisOlustur($urun, 1);
        $this->get(route('odeme.show', $siparis))->assertStatus(200);

        $odeme = Odeme::query()->where('siparis_id', $siparis->id)->where('provider', Odeme::PROVIDER_PAYTR)->firstOrFail();
        $providerRef = (string) $odeme->provider_ref;
        $callbackId = 'CB_AMT';
        $status = 'success';
        $wrongAmount = (string) (((int) round(((float) $siparis->genel_toplam) * 100)) + 100);
        $hash = $this->paytrHash($merchantKey, $merchantSalt, $callbackId, $providerRef, $status, $wrongAmount);

        $this->post(route('odeme.webhook.callback', ['provider' => 'paytr']), [
            'hash' => $hash,
            'callback_id' => $callbackId,
            'merchant_oid' => $providerRef,
            'status' => $status,
            'total_amount' => $wrongAmount,
        ])->assertStatus(422);

        $siparis->refresh();
        $this->assertSame(Siparis::DURUM_ONAY_BEKLIYOR, $siparis->durum);
    }

    public function test_odeme_provider_factory_dogru_servisi_doner(): void
    {
        $factory = app(OdemeProviderFactory::class);
        $this->assertInstanceOf(PaytrOdemeServisi::class, $factory->make('paytr'));
        $this->assertInstanceOf(IyzicoOdemeServisi::class, $factory->make('iyzico'));
    }

    public function test_iyzico_callback_basarili_ve_idempotent(): void
    {
        $firma = $this->firmaOlustur('Firma Iyzico CB');
        $urun = $this->urunHazirla($firma, 10);

        [$cari, $kasa] = $this->cariVeKasaOlustur($firma);
        config([
            'ecommerce.tahsilat_cari_id' => $cari->id,
            'ecommerce.tahsilat_kasa_id' => $kasa->id,
        ]);

        $apiKey = 'iyz_api_2';
        $secretKey = 'iyz_secret_2';
        $baseUrl = 'https://sandbox-api.iyzipay.com';
        $this->odemeAyarlariniYazIyzico($firma, $apiKey, $secretKey, $baseUrl);

        Http::fake(function ($request) {
            if (str_contains((string) $request->url(), '/payment/iyzipos/checkoutform/initialize/auth/ecom')) {
                return Http::response([
                    'status' => 'success',
                    'paymentPageUrl' => 'https://iyzico.test/checkout2',
                ], 200);
            }

            return Http::response(['status' => 'success'], 200);
        });

        $siparis = $this->siparisOlustur($urun, 1);
        $this->get(route('odeme.show', $siparis))->assertRedirect('https://iyzico.test/checkout2');

        $odeme = Odeme::query()->where('siparis_id', $siparis->id)->where('provider', Odeme::PROVIDER_IYZICO)->firstOrFail();
        $providerRef = (string) $odeme->provider_ref;

        $eventType = 'PAYMENT_API';
        $paymentId = 'PAYMENT_123';
        $status = 'SUCCESS';

        // Webhook direct format signature (iyziEventType + paymentId + paymentConversationId + status)
        $message = $secretKey.$eventType.$paymentId.$providerRef.$status;
        $expectedSignature = hash_hmac('sha256', $message, $secretKey);

        $payload = [
            'paymentConversationId' => $providerRef,
            'merchantId' => 'm1',
            'paymentId' => $paymentId,
            'status' => $status,
            'iyziEventType' => $eventType,
            'iyziPaymentId' => $paymentId,
        ];

        $this->postJson(
            route('odeme.webhook.callback', ['provider' => 'iyzico']),
            $payload,
            ['X-Iyz-Signature-V3' => $expectedSignature],
        )->assertStatus(200)->assertSeeText('OK');

        $siparis->refresh();
        $this->assertSame(Siparis::DURUM_ONAYLANDI_YENI, $siparis->durum);

        $logCount = SiparisGecmisi::query()->where('siparis_id', $siparis->id)->where('olay', SiparisGecmisi::OLAY_ODEME_BASARILI)->count();
        $this->assertSame(1, $logCount);

        // Duplicate webhook
        $this->postJson(
            route('odeme.webhook.callback', ['provider' => 'iyzico']),
            $payload,
            ['X-Iyz-Signature-V3' => $expectedSignature],
        )->assertStatus(200)->assertSeeText('OK');

        $logCount2 = SiparisGecmisi::query()->where('siparis_id', $siparis->id)->where('olay', SiparisGecmisi::OLAY_ODEME_BASARILI)->count();
        $this->assertSame(1, $logCount2);
    }
}
