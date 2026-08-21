<?php

namespace Tests\Feature\Monitoring;

use App\Filament\Clusters\Muhasebe\Pages\MuhasebeDashboardSayfasi;
use App\Models\Ecommerce\Siparis;
use App\Models\Firma;
use App\Models\Muhasebe\StokKarti;
use App\Models\SistemOlayi;
use App\Services\SistemOlayServisi;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SistemOlaylariTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    private function firmaOlustur(string $kod): Firma
    {
        return Firma::query()->create([
            'ad' => 'Test '.$kod,
            'kisa_ad' => $kod,
            'firma_kodu' => $kod.'-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
    }

    public function test_error_olay_kaydedilir(): void
    {
        $firma = $this->firmaOlustur('SO1');

        app(SistemOlayServisi::class)->olayKaydet('odeme.callback.hata', 'error', 'Callback hata', [
            'firma_id' => $firma->id,
            'siparis_id' => 10,
        ]);

        $this->assertDatabaseHas('sistem_olaylari', [
            'tip' => 'odeme.callback.hata',
            'seviye' => 'error',
            'firma_id' => $firma->id,
        ]);
    }

    public function test_warning_olay_kaydedilir(): void
    {
        $firma = $this->firmaOlustur('SO2');

        app(SistemOlayServisi::class)->olayKaydet('stok.kritik_stok_altinda', 'warning', 'Kritik stok', [
            'firma_id' => $firma->id,
            'stok_id' => 7,
        ]);

        $this->assertDatabaseHas('sistem_olaylari', [
            'tip' => 'stok.kritik_stok_altinda',
            'seviye' => 'warning',
            'firma_id' => $firma->id,
        ]);
    }

    public function test_dashboard_olaylari_gosterir(): void
    {
        $firma = $this->firmaOlustur('SO3');
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        SistemOlayi::query()->withoutGlobalScopes()->create([
            'tip' => 'finans.otomatik_dagitim_hatasi',
            'seviye' => 'error',
            'mesaj' => 'Dagitim hatasi',
            'context' => ['firma_id' => $firma->id],
            'firma_id' => $firma->id,
        ]);

        Cache::forget('muhasebe:dashboard-sistem-detaylari:v1:firma:'.$firma->id);

        $sayfa = app(MuhasebeDashboardSayfasi::class);
        $ozet = $sayfa->sistemDetaylari();

        $this->assertNotEmpty($ozet['sistem_uyarilari']);
        $this->assertSame('finans.otomatik_dagitim_hatasi', $ozet['sistem_uyarilari'][0]->tip);
    }

    public function test_health_endpoint_dogru_veri_doner(): void
    {
        $firma = $this->firmaOlustur('SO4');
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        SistemOlayi::query()->withoutGlobalScopes()->create([
            'tip' => 'odeme.callback.hata',
            'seviye' => 'critical',
            'mesaj' => 'Kritik callback',
            'context' => ['firma_id' => $firma->id],
            'firma_id' => $firma->id,
        ]);

        Siparis::query()->create([
            'siparis_no' => 'S-001',
            'firma_id' => $firma->id,
            'musteri_ad_soyad' => 'A',
            'musteri_email' => 'a@test.local',
            'musteri_telefon' => '555',
            'teslimat_adresi' => 'Adres',
            'para_birimi' => 'TRY',
            'ara_toplam' => '100',
            'kdv_toplam' => '0',
            'genel_toplam' => '100',
            'durum' => Siparis::DURUM_ONAY_BEKLIYOR,
            'odeme_suresi_bitis_at' => now()->subMinutes(5),
            'stok_dusuldu_mi' => false,
            'odeme_deneme_sayisi' => 0,
        ]);

        StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'S-NEG',
            'ad' => 'Negatif',
            'tur' => 'diger',
            'durum' => 'aktif',
            'stok_takip' => true,
            'stok_miktari' => '-1',
            'negative_flag' => true,
        ]);

        $this->getJson(route('sistem.health'))
            ->assertOk()
            ->assertJsonPath('timeout_olmus_siparis_sayisi', 1)
            ->assertJsonPath('negatif_stok_sayisi', 1)
            ->assertJsonPath('odeme_bekleyen_siparis_sayisi', 1);
    }

    public function test_callback_hatasi_olay_olusturur(): void
    {
        $this->post(route('odeme.webhook.callback', ['provider' => 'invalid-provider']), [])
            ->assertStatus(400);

        $this->assertDatabaseHas('sistem_olaylari', [
            'tip' => 'odeme.callback.hata',
            'seviye' => 'warning',
        ]);
    }
}
