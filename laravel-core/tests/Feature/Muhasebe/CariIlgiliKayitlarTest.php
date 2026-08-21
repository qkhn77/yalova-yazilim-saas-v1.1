<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\CariAdresi;
use App\Models\Muhasebe\CariBankaHesabi;
use App\Models\Muhasebe\CariYetkiliKisi;
use App\Models\User;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CariIlgiliKayitlarTest extends TestCase
{
    use RefreshDatabase;

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

    private function cariOlustur(Firma $firma): Cari
    {
        return Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'CARI-'.uniqid(),
            'ad' => 'Test Cari',
            'website' => 'https://example.test',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);
    }

    private function tenantKullaniciOlustur(): User
    {
        return User::query()->create([
            'name' => 'Cari Test Kullanıcısı',
            'email' => 'cari-ilgili-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => false,
        ]);
    }

    public function test_cari_iletisim_adres_ve_banka_kayitlarini_ayri_tablolarda_tutar(): void
    {
        $firma = $this->firmaOlustur('CIR');
        $kullanici = $this->tenantKullaniciOlustur();
        $this->actingAs($kullanici);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $cari = $this->cariOlustur($firma);

        foreach (range(1, 6) as $sira) {
            CariYetkiliKisi::query()->create([
                'firma_id' => $firma->id,
                'cari_id' => $cari->id,
                'ad_soyad' => 'Yetkili '.$sira,
                'gorevi' => 'Görev '.$sira,
                'telefon' => '+90 555 000 00 '.str_pad((string) $sira, 2, '0', STR_PAD_LEFT),
                'email' => 'yetkili'.$sira.'@example.test',
                'sira' => $sira,
            ]);
        }

        CariAdresi::query()->create([
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
            'baslik' => 'Fatura Adresi',
            'tur' => 'Fatura',
            'adres' => 'Test adresi',
            'il' => 'Yalova',
            'sira' => 1,
        ]);

        CariAdresi::query()->create([
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
        ]);

        $ilkHesap = CariBankaHesabi::query()->create([
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
            'hesap_adi' => 'Ana hesap',
            'banka_adi' => 'Test Bankası',
            'sube_adi' => 'Merkez',
            'hesap_no' => '123456',
            'para_birimi' => 'TRY',
            'varsayilan_mi' => true,
            'sira' => 1,
        ]);

        $ikinciHesap = CariBankaHesabi::query()->create([
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
            'hesap_adi' => 'Yedek hesap',
            'banka_adi' => 'Başka Banka',
            'sube_adi' => 'Şube',
            'hesap_no' => '654321',
            'para_birimi' => 'EUR',
            'varsayilan_mi' => true,
            'sira' => 2,
        ]);

        CariBankaHesabi::query()->create([
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
        ]);

        $this->assertSame('https://example.test', $cari->fresh()->website);
        $this->assertCount(6, $cari->fresh()->yetkiliKisiler);
        $this->assertCount(2, $cari->fresh()->adresler);
        $this->assertCount(3, $cari->fresh()->bankaHesaplari);
        $this->assertFalse((bool) $ilkHesap->fresh()->varsayilan_mi);
        $this->assertTrue((bool) $ikinciHesap->fresh()->varsayilan_mi);
    }

    public function test_cari_ilgili_kayitlari_firma_scope_ile_izole_edilir(): void
    {
        $firmaA = $this->firmaOlustur('CIA');
        $firmaB = $this->firmaOlustur('CIB');
        $cariB = $this->cariOlustur($firmaB);

        CariYetkiliKisi::query()->create([
            'firma_id' => $firmaB->id,
            'cari_id' => $cariB->id,
            'ad_soyad' => 'Başka Firma Yetkilisi',
        ]);

        CariAdresi::query()->create([
            'firma_id' => $firmaB->id,
            'cari_id' => $cariB->id,
            'baslik' => 'Merkez adres',
            'tur' => 'Merkez',
            'adres' => 'Başka firma adresi',
        ]);

        CariBankaHesabi::query()->create([
            'firma_id' => $firmaB->id,
            'cari_id' => $cariB->id,
            'hesap_adi' => 'Başka firma hesabı',
            'banka_adi' => 'Test Bankası',
            'sube_adi' => 'Merkez',
            'hesap_no' => '000001',
            'para_birimi' => 'TRY',
        ]);

        $kullanici = $this->tenantKullaniciOlustur();
        $this->actingAs($kullanici);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firmaA->id]);

        $this->assertSame(0, CariYetkiliKisi::query()->count());
        $this->assertSame(0, CariAdresi::query()->count());
        $this->assertSame(0, CariBankaHesabi::query()->count());
    }

    public function test_cari_kodu_firma_bazli_olarak_arka_planda_uretilir(): void
    {
        $firma = $this->firmaOlustur('CKD');
        $kullanici = $this->tenantKullaniciOlustur();
        $this->actingAs($kullanici);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $ilk = Cari::query()->create([
            'firma_id' => $firma->id,
            'ad' => 'İlk Test Cari',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        $ikinci = Cari::query()->create([
            'firma_id' => $firma->id,
            'ad' => 'İkinci Test Cari',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        $this->assertMatchesRegularExpression('/^CR-\d+$/', (string) $ikinci->kod);
        $this->assertSame('CR-1000', $ilk->kod);
        $this->assertSame('CR-1001', $ikinci->kod);
        $this->assertNotSame($ilk->kod, $ikinci->kod);
    }
}
