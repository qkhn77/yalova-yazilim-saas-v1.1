<?php

namespace Tests\Feature\Hardening;

use App\Models\Ecommerce\Siparis;
use App\Models\Firma;
use App\Models\FirmaModulu;
use App\Models\Modul;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\SistemOlayi;
use App\Models\User;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Services\EcommerceFirmaAyarServisi;
use App\Services\EcommerceOdemeFirmaAyarServisi;
use App\Services\FirmaAyarDeposu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinalHardeningFailSafeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    private function firma(): Firma
    {
        $firma = Firma::query()->create([
            'ad' => 'Hardening Firma',
            'kisa_ad' => 'HF-'.uniqid(),
            'firma_kodu' => 'HFK-'.uniqid(),
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
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);
        $kasa = KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'EC-K-'.uniqid(),
            'ad' => 'E-ticaret Kasa',
            'para_birimi' => 'TRY',
            'durum' => HesapDurumu::Aktif->value,
        ]);

        app(EcommerceFirmaAyarServisi::class)->kaydetAyarlar((int) $firma->id, [
            'ecommerce_etkin_mi' => true,
            'ecommerce_tahsilat_cari_id' => $cari->id,
            'ecommerce_tahsilat_kasa_id' => $kasa->id,
            'ecommerce_otomatik_genel_kasa_kullan' => false,
            'ecommerce_cron_fallback_etkin_mi' => true,
        ]);
    }

    public function test_health_endpoint_throttle_abuse_korur(): void
    {
        $last = null;
        for ($i = 0; $i < 31; $i++) {
            $last = $this->get(route('sistem.health'));
        }
        $last?->assertStatus(429);
    }

    public function test_cron_fallback_token_bos_iken_fail_safe_503_doner(): void
    {
        config(['ecommerce.cron_fallback_token' => '']);
        $this->get(route('ecommerce.cron.odeme-zaman-asimi'))
            ->assertStatus(503);

        $this->assertDatabaseHas('sistem_olaylari', [
            'tip' => 'cron.fallback.token_eksik',
            'seviye' => 'critical',
        ]);
    }

    public function test_eksik_veya_hatali_provider_config_erken_yakalanir(): void
    {
        $firma = $this->firma();
        $depo = app(FirmaAyarDeposu::class);
        $depo->yaz($firma->id, 'ecommerce_odeme_aktif_mi', true);
        $depo->yaz($firma->id, 'ecommerce_odeme_provider', 'gecersiz_provider');

        $this->expectException(ValidationException::class);
        app(EcommerceOdemeFirmaAyarServisi::class)->kontrolOdemeBaslatmaAyarlarVeyaNull($firma->id);
    }

    public function test_secret_odeme_ayari_degisiminde_audit_olayi_olusur(): void
    {
        $firma = $this->firma();
        $user = User::factory()->create(['super_admin_mi' => true]);
        if (! $user instanceof User) {
            $this->fail('User olusturulamadi.');
        }
        $this->actingAs($user);

        app(EcommerceOdemeFirmaAyarServisi::class)->kaydetAyarlar($firma->id, [
            'ecommerce_odeme_aktif_mi' => true,
            'ecommerce_odeme_provider' => 'paytr',
            'paytr_merchant_key' => 'secret-key',
            'paytr_merchant_salt' => 'secret-salt',
        ]);

        $kayit = SistemOlayi::query()->withoutGlobalScopes()
            ->where('tip', 'ayar.odeme_degisti')
            ->latest('id')
            ->first();

        $this->assertNotNull($kayit);
        $this->assertSame($firma->id, (int) $kayit->firma_id);
        $this->assertSame($user->id, (int) ($kayit->context['kullanici_id'] ?? 0));
        $this->assertTrue((bool) ($kayit->context['secret_guncellendi']['paytr_merchant_key'] ?? false));
    }

    public function test_yetkisiz_kullanici_kritik_odeme_aksiyonuna_erisemez(): void
    {
        $firma = $this->firma();
        $siparis = Siparis::query()->create([
            'siparis_no' => 'SIP-HARD',
            'firma_id' => $firma->id,
            'kullanici_id' => null,
            'musteri_ad_soyad' => 'X',
            'musteri_email' => 'x@test.local',
            'musteri_telefon' => '555',
            'teslimat_adresi' => 'Adres',
            'para_birimi' => 'TRY',
            'ara_toplam' => '100',
            'kdv_toplam' => '0',
            'genel_toplam' => '100',
            'durum' => Siparis::DURUM_ONAY_BEKLIYOR,
            'stok_dusuldu_mi' => false,
            'odeme_deneme_sayisi' => 0,
        ]);

        $this->post(route('odeme.basarili', $siparis))->assertStatus(403);
    }
}
