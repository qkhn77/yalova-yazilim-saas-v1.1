<?php

namespace Tests\Feature\Sekreter;

use App\Models\Firma;
use App\Models\Iletisim\KullaniciBildirimi;
use App\Models\SekreterGorevi;
use App\Models\SekreterHatirlatmasi;
use App\Models\User;
use App\Policies\SekreterGoreviPolicy;
use App\Services\ModulErisimService;
use App\Services\SekreterKayitKuraliServisi;
use App\Services\SekreterHatirlatmaServisi;
use App\Services\TenantContextService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SekreterModuluTest extends TestCase
{
    use RefreshDatabase;

    private function firmaOlustur(string $kod): Firma
    {
        return Firma::query()->create([
            'ad' => 'Sekreter '.$kod,
            'kisa_ad' => $kod,
            'firma_kodu' => $kod.'-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
    }

    private function kullaniciOlustur(string $etiket): User
    {
        return User::query()->create([
            'name' => $etiket,
            'email' => strtolower($etiket).'-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => false,
        ]);
    }

    public function test_gorev_aktif_firma_kapsaminda_izole_edilir(): void
    {
        $firmaA = $this->firmaOlustur('SKA');
        $firmaB = $this->firmaOlustur('SKB');
        $kullanici = $this->kullaniciOlustur('sekreter');
        $gorev = SekreterGorevi::query()->create([
            'firma_id' => $firmaB->id,
            'olusturan_kullanici_id' => $kullanici->id,
            'baslik' => 'B firmasının görevi',
            'tarih' => today(),
        ]);

        $this->actingAs($kullanici);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firmaA->id]);

        $this->assertNull(SekreterGorevi::query()->find($gorev->id));
    }

    public function test_bekleyen_gecmis_tarihli_gorev_gecikmis_sayilir(): void
    {
        $firma = $this->firmaOlustur('SKC');
        $kullanici = $this->kullaniciOlustur('gecikme');
        $this->actingAs($kullanici);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $gorev = SekreterGorevi::query()->create([
            'firma_id' => $firma->id,
            'olusturan_kullanici_id' => $kullanici->id,
            'baslik' => 'Geciken görev',
            'tarih' => today()->subDay(),
            'durum' => 'bekliyor',
        ]);

        $this->assertTrue($gorev->fresh()->gecikti_mi);
    }

    public function test_tekrarli_gorev_bir_sonraki_etkinlik_zamanina_tasinir(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:00:00'));
        $firma = $this->firmaOlustur('SKD');
        $kullanici = $this->kullaniciOlustur('tekrar');

        $gorev = new SekreterGorevi([
            'firma_id' => $firma->id,
            'olusturan_kullanici_id' => $kullanici->id,
            'baslik' => 'Günlük görev',
            'tarih' => '2026-08-09',
            'saat' => '11:00',
            'tekrar_tipi' => 'gunluk',
        ]);

        $zaman = app(SekreterHatirlatmaServisi::class)->etkinlikZamani($gorev);

        $this->assertSame('2026-08-10 11:00:00', $zaman->format('Y-m-d H:i:s'));
        Carbon::setTestNow();
    }

    public function test_eski_tekrarli_gorev_ajanda_araliginda_gorunur(): void
    {
        $firma = $this->firmaOlustur('SKH');
        $kullanici = $this->kullaniciOlustur('ajanda');
        $gorev = new SekreterGorevi([
            'firma_id' => $firma->id,
            'olusturan_kullanici_id' => $kullanici->id,
            'baslik' => 'Eski günlük görev',
            'tarih' => today()->subDays(30),
            'saat' => '09:00',
            'tekrar_tipi' => 'gunluk',
        ]);

        $olaylar = app(SekreterHatirlatmaServisi::class)->araliktakiEtkinlikler($gorev, today()->startOfMonth(), today()->endOfMonth());

        $this->assertNotEmpty($olaylar);
        $this->assertTrue(collect($olaylar)->every(fn (array $olay): bool => $olay['zaman']->betweenIncluded(today()->startOfMonth(), today()->endOfMonth())));
    }

    public function test_hatirlatma_komutu_bildirimi_kayitli_gonderim_gunluguyle_tekilleştirir(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:00:00'));
        $firma = $this->firmaOlustur('SKE');
        $kullanici = $this->kullaniciOlustur('bildirim');
        $this->actingAs($kullanici);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $gorev = SekreterGorevi::query()->create([
            'firma_id' => $firma->id,
            'olusturan_kullanici_id' => $kullanici->id,
            'baslik' => 'Yaklaşan görev',
            'tarih' => '2026-08-10',
            'saat' => '10:05',
            'hatirlatma_tipi' => '5_dk',
        ]);

        $this->mock(ModulErisimService::class, function ($mock): void {
            $mock->shouldReceive('modulErisilebilirMi')->andReturnTrue();
        });

        $this->artisan('sekreter:hatirlatmalari-gonder')->assertSuccessful();
        $this->artisan('sekreter:hatirlatmalari-gonder')->assertSuccessful();

        $this->assertDatabaseCount('kullanici_bildirimleri', 1);
        $this->assertDatabaseCount('sekreter_hatirlatmalari', 1);
        $this->assertDatabaseHas('sekreter_hatirlatmalari', ['hatirlanabilir_id' => $gorev->id]);
        Carbon::setTestNow();
    }

    public function test_sekreter_modulu_kapaliyken_policy_erisim_vermez(): void
    {
        $firma = $this->firmaOlustur('SKF');
        $kullanici = $this->kullaniciOlustur('yetki');
        $this->actingAs($kullanici);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $this->mock(ModulErisimService::class, function ($mock): void {
            $mock->shouldReceive('modulErisilebilirMi')->with($this->anything(), 'sekreter')->andReturnFalse();
        });

        $this->assertFalse(app(SekreterGoreviPolicy::class)->viewAny($kullanici));
    }

    public function test_muhasebe_kapaliyken_cari_baglantisi_reddedilir(): void
    {
        $firma = $this->firmaOlustur('SKG');
        $kullanici = $this->kullaniciOlustur('entegrasyon');
        $this->actingAs($kullanici);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $this->mock(ModulErisimService::class, function ($mock): void {
            $mock->shouldReceive('modulErisilebilirMi')->with($this->anything(), 'muhasebe')->andReturnFalse();
        });

        $gorev = new SekreterGorevi([
            'firma_id' => $firma->id,
            'cari_id' => 123,
        ]);

        $this->expectException(ValidationException::class);
        app(SekreterKayitKuraliServisi::class)->kontrolEt($gorev);
    }
}
