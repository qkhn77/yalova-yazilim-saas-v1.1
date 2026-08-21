<?php

namespace Tests\Feature\Urun;

use App\Filament\Pages\FirmaAyarlariSayfasi;
use App\Models\Firma;
use App\Models\FirmaAyari;
use App\Models\User;
use App\Services\EcommerceOdemeFirmaAyarServisi;
use App\Services\FirmaAyarDeposu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class OdemeSecretGuvenlikTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretler_dbde_plain_text_tutulmaz_ve_cozulur(): void
    {
        $firma = Firma::query()->create([
            'ad' => 'Secret Test Firma',
            'kisa_ad' => 'ST-'.uniqid(),
            'firma_kodu' => 'F-ST-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);

        $depo = app(FirmaAyarDeposu::class);
        $depo->yaz($firma->id, 'paytr_merchant_key', 'paytr_secret_value');
        $depo->yaz($firma->id, 'iyzico_secret_key', 'iyzico_secret_value');

        $rawPaytr = (string) FirmaAyari::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firma->id)
            ->where('anahtar', 'paytr_merchant_key')
            ->firstOrFail()
            ->getRawOriginal('deger');

        $rawIyzico = (string) FirmaAyari::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firma->id)
            ->where('anahtar', 'iyzico_secret_key')
            ->firstOrFail()
            ->getRawOriginal('deger');

        $this->assertStringNotContainsString('paytr_secret_value', $rawPaytr);
        $this->assertStringNotContainsString('iyzico_secret_value', $rawIyzico);
        $this->assertSame('paytr_secret_value', $depo->oku($firma->id, 'paytr_merchant_key'));
        $this->assertSame('iyzico_secret_value', $depo->oku($firma->id, 'iyzico_secret_key'));
    }

    public function test_firma_ayari_serialization_hassas_degeri_maskeler(): void
    {
        $firma = Firma::query()->create([
            'ad' => 'Secret Mask Firma',
            'kisa_ad' => 'SM-'.uniqid(),
            'firma_kodu' => 'F-SM-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);

        $kayit = FirmaAyari::query()->create([
            'firma_id' => $firma->id,
            'anahtar' => 'paytr_merchant_key',
            'deger' => ['deger' => 'enc:v1:dummy'],
        ]);

        $dizi = $kayit->toArray();
        $this->assertSame('***', $dizi['deger']['deger'] ?? null);
        $this->assertStringContainsString('"***"', $kayit->toJson());
    }

    public function test_kaydet_ekraninda_secret_alanlari_yoksa_var_olanlar_silinmez(): void
    {
        $firma = Firma::query()->create([
            'ad' => 'Secret Preserve Firma',
            'kisa_ad' => 'SP-'.uniqid(),
            'firma_kodu' => 'F-SP-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);

        $depo = app(FirmaAyarDeposu::class);
        $depo->yaz($firma->id, 'paytr_merchant_key', 'onceki_key');
        $depo->yaz($firma->id, 'paytr_merchant_salt', 'onceki_salt');

        app(EcommerceOdemeFirmaAyarServisi::class)->kaydetAyarlar($firma->id, [
            'ecommerce_odeme_aktif_mi' => true,
            'ecommerce_odeme_provider' => 'paytr',
            'test_modu' => false,
        ]);

        $this->assertSame('onceki_key', $depo->oku($firma->id, 'paytr_merchant_key'));
        $this->assertSame('onceki_salt', $depo->oku($firma->id, 'paytr_merchant_salt'));
    }

    public function test_secret_goruntuleme_super_admin_ile_sinirli(): void
    {
        $page = new FirmaAyarlariSayfasi;
        $metot = new ReflectionMethod(FirmaAyarlariSayfasi::class, 'odemeSecretGoruntulebilirMi');
        $metot->setAccessible(true);

        /** @var User $normal */
        $normal = User::factory()->create(['super_admin_mi' => false]);
        $this->actingAs($normal);
        $this->assertFalse((bool) $metot->invoke($page));

        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['super_admin_mi' => true]);
        $this->actingAs($superAdmin);
        $this->assertTrue((bool) $metot->invoke($page));
    }

    public function test_hata_mesajlari_secret_degerlerini_icermez(): void
    {
        $firma = Firma::query()->create([
            'ad' => 'Secret Log Firma',
            'kisa_ad' => 'SL-'.uniqid(),
            'firma_kodu' => 'F-SL-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);

        app(EcommerceOdemeFirmaAyarServisi::class)->kaydetAyarlar($firma->id, [
            'ecommerce_odeme_aktif_mi' => true,
            'ecommerce_odeme_provider' => 'paytr',
            'test_modu' => false,
            'paytr_merchant_id' => 'm1',
            // merchant_key / salt bilinçli eksik
        ]);

        try {
            app(EcommerceOdemeFirmaAyarServisi::class)->kontrolOdemeBaslatmaAyarlarVeyaNull($firma->id);
            $this->fail('ValidationException bekleniyordu.');
        } catch (ValidationException $e) {
            $msg = json_encode($e->errors(), JSON_UNESCAPED_UNICODE) ?: '';
            $this->assertStringNotContainsString('paytr_merchant_key', $msg);
            $this->assertStringNotContainsString('my_critical_key', $msg);
            $this->assertStringContainsString('PayTR ayarları eksik', $msg);
        }
    }
}
