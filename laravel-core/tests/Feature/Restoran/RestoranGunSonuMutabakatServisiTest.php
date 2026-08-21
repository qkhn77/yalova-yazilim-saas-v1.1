<?php

namespace Tests\Feature\Restoran;

use App\Models\Firma;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\PosHesabi;
use App\Models\Restoran\RestoranGunSonuKapanisi;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\User;
use App\Services\Restoran\RestoranGunSonuMutabakatServisi;
use App\Services\Restoran\RestoranTahsilatServisi;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RestoranGunSonuMutabakatServisiTest extends TestCase
{
    use RefreshDatabase;

    public function test_gun_sonu_mutabakati_tahsilat_ve_muhasebe_hareketlerini_karsilastirir(): void
    {
        $firma = $this->firmaOlustur('RGSM');
        $kasa = KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'KASA-GSM',
            'ad' => 'Gun Sonu Kasa',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);
        $pos = PosHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'POS-GSM',
            'ad' => 'Gun Sonu POS',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_no' => 'AD-GSM-1',
            'acilis_at' => now(),
            'durum' => RestoranAdisyonu::DURUM_ACIK,
            'genel_toplam' => 500,
            'para_birimi' => 'TRY',
        ]);

        $this->muhasebeBaglamiHazirla($firma);

        app(RestoranTahsilatServisi::class)->parcaliTahsilatOlustur($adisyon, [
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
            'tutar' => 200,
        ]);
        app(RestoranTahsilatServisi::class)->parcaliTahsilatOlustur($adisyon->refresh(), [
            'odeme_kanali' => 'pos',
            'pos_hesap_id' => $pos->id,
            'tutar' => 300,
        ]);

        $ozet = app(RestoranGunSonuMutabakatServisi::class)->gunlukOzet((int) $firma->id, now());

        $this->assertTrue($ozet['mutabik_mi']);
        $this->assertSame(500.0, $ozet['toplam_tahsilat']);
        $this->assertSame(500.0, $ozet['toplam_muhasebe']);
        $this->assertSame(0.0, $ozet['toplam_fark']);
        $this->assertSame(200.0, collect($ozet['kanallar'])->firstWhere('kanal', 'kasa')['tahsilat_tutari']);
        $this->assertSame(300.0, collect($ozet['kanallar'])->firstWhere('kanal', 'pos')['muhasebe_tutari']);
    }

    public function test_iptal_edilen_tahsilat_gun_sonu_mutabakatinda_aktif_tutar_sayilmaz(): void
    {
        $firma = $this->firmaOlustur('RGSI');
        $kasa = KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'KASA-GSI',
            'ad' => 'Gun Sonu Iptal Kasa',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_no' => 'AD-GSI-1',
            'acilis_at' => now(),
            'durum' => RestoranAdisyonu::DURUM_ACIK,
            'genel_toplam' => 200,
            'para_birimi' => 'TRY',
        ]);

        $this->muhasebeBaglamiHazirla($firma);

        $tahsilat = app(RestoranTahsilatServisi::class)->parcaliTahsilatOlustur($adisyon, [
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
            'tutar' => 200,
        ]);
        app(RestoranTahsilatServisi::class)->tahsilatIptalEt($tahsilat, 'Gun sonu testi');

        $ozet = app(RestoranGunSonuMutabakatServisi::class)->gunlukOzet((int) $firma->id, now());

        $this->assertTrue($ozet['mutabik_mi']);
        $this->assertSame(0.0, $ozet['toplam_tahsilat']);
        $this->assertSame(0.0, $ozet['toplam_muhasebe']);
    }

    public function test_gun_sonu_kapanisi_kaydedilir_ve_tekrarinda_guncellenir(): void
    {
        $firma = $this->firmaOlustur('RGSK');
        $kasa = KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'KASA-GSK',
            'ad' => 'Gun Sonu Kapanis Kasa',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_no' => 'AD-GSK-1',
            'acilis_at' => now(),
            'durum' => RestoranAdisyonu::DURUM_ACIK,
            'genel_toplam' => 120,
            'para_birimi' => 'TRY',
        ]);
        $user = $this->muhasebeBaglamiHazirla($firma);

        app(RestoranTahsilatServisi::class)->parcaliTahsilatOlustur($adisyon, [
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
            'tutar' => 120,
        ]);

        $servis = app(RestoranGunSonuMutabakatServisi::class);
        $ilk = $servis->kapanisKaydet((int) $firma->id, now(), null, 'Ilk kapanis', (int) $user->id);
        $ikinci = $servis->kapanisKaydet((int) $firma->id, now(), null, 'Guncel kapanis', (int) $user->id);

        $this->assertSame($ilk->id, $ikinci->id);
        $this->assertTrue((bool) $ikinci->mutabik_mi);
        $this->assertSame('120.00', (string) $ikinci->toplam_tahsilat);
        $this->assertSame('Guncel kapanis', $ikinci->notlar);
        $this->assertSame(1, RestoranGunSonuKapanisi::withoutGlobalScopes()->where('firma_id', $firma->id)->count());
        $this->assertNotNull($servis->gunlukOzet((int) $firma->id, now())['kapanis']);
    }

    public function test_farkli_gun_sonu_kapanisinda_aciklama_zorunludur(): void
    {
        $firma = $this->firmaOlustur('RGSF');
        $kasa = KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'KASA-GSF',
            'ad' => 'Gun Sonu Fark Kasa',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_no' => 'AD-GSF-1',
            'acilis_at' => now(),
            'durum' => RestoranAdisyonu::DURUM_ACIK,
            'genel_toplam' => 80,
            'para_birimi' => 'TRY',
        ]);
        $this->muhasebeBaglamiHazirla($firma);

        $tahsilat = app(RestoranTahsilatServisi::class)->parcaliTahsilatOlustur($adisyon, [
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
            'tutar' => 80,
        ]);
        $tahsilat->forceFill(['durum' => 'aktif'])->save();
        $tahsilat->finansHareketi()->withoutGlobalScopes()->firstOrFail()->forceFill(['durum' => 'iptal'])->save();

        try {
            app(RestoranGunSonuMutabakatServisi::class)->kapanisKaydet((int) $firma->id, now());
            $this->fail('Fark aciklamasi validasyonu bekleniyordu.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('fark_aciklamasi', $exception->errors());
        }

        $kapanis = app(RestoranGunSonuMutabakatServisi::class)->kapanisKaydet((int) $firma->id, now(), 'POS raporu bekleniyor.');

        $this->assertFalse((bool) $kapanis->mutabik_mi);
        $this->assertSame('POS raporu bekleniyor.', $kapanis->fark_aciklamasi);
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

    private function muhasebeBaglamiHazirla(Firma $firma): User
    {
        $user = User::factory()->create([
            'super_admin_mi' => true,
        ]);
        $this->actingAs($user);
        app(TenantContextService::class)->firmaAyarla($firma);

        return $user;
    }
}
