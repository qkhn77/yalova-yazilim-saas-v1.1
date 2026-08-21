<?php

namespace Tests\Feature\Restoran;

use App\Models\Firma;
use App\Models\Restoran\RestoranAdisyonKalemi;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Restoran\RestoranMasasi;
use App\Models\Restoran\RestoranMenuKategorisi;
use App\Models\Restoran\RestoranMenuUrunu;
use App\Models\Sube;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RestoranQrMenuEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        URL::forceRootUrl('http://localhost');
    }

    public function test_public_qr_menu_sadece_gorunur_urunleri_dondurur(): void
    {
        $firma = $this->firmaOlustur('QR-END');
        $sube = $this->subeOlustur($firma, 'MRK');
        $kategori = RestoranMenuKategorisi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'ad' => 'Kahvalti',
            'siralama' => 2,
        ]);

        RestoranMenuUrunu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kategori_id' => $kategori->id,
            'ad' => 'Serpme Kahvalti',
            'aciklama' => 'Iki kisilik servis',
            'fiyat' => 450,
            'kdv_orani' => 10,
            'siralama' => 1,
        ]);
        RestoranMenuUrunu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kategori_id' => $kategori->id,
            'ad' => 'Gizli Urun',
            'fiyat' => 100,
            'qr_menu_gorunur_mu' => false,
        ]);

        $response = $this->getJson('/restoran/qr-menu/'.$firma->firma_kodu.'?sube_id='.$sube->id);

        $response
            ->assertOk()
            ->assertJsonPath('firma.id', $firma->id)
            ->assertJsonPath('sube_id', $sube->id)
            ->assertJsonPath('kategoriler.0.ad', 'Kahvalti')
            ->assertJsonPath('kategoriler.0.urunler.0.ad', 'Serpme Kahvalti')
            ->assertJsonPath('kategoriler.0.urunler.0.fiyat', '450.00')
            ->assertJsonMissing(['ad' => 'Gizli Urun']);
    }

    public function test_public_qr_menu_firma_disi_sube_ile_erisim_vermez(): void
    {
        $firma = $this->firmaOlustur('QR-A');
        $digerFirma = $this->firmaOlustur('QR-B');
        $digerSube = $this->subeOlustur($digerFirma, 'DSB');

        $this->getJson('/restoran/qr-menu/'.$firma->firma_kodu.'?sube_id='.$digerSube->id)
            ->assertNotFound();
    }

    public function test_public_qr_menu_pasif_ve_onaysiz_firmayi_gostermez(): void
    {
        $pasifFirma = $this->firmaOlustur('QR-PASIF', Firma::DURUM_ASKIDA, true);
        $onaysizFirma = $this->firmaOlustur('QR-ONAYSIZ', Firma::DURUM_AKTIF, false);

        $this->getJson('/restoran/qr-menu/'.$pasifFirma->firma_kodu)
            ->assertNotFound();
        $this->getJson('/restoran/qr-menu/'.$onaysizFirma->firma_kodu)
            ->assertNotFound();
    }

    public function test_qr_siparis_masa_icin_adisyon_acar_ve_menu_urunu_ekler(): void
    {
        $firma = $this->firmaOlustur('QR-SIP');
        $sube = $this->subeOlustur($firma, 'MRK');
        $masa = $this->masaOlustur($firma, $sube, 'M1');
        $urun = $this->menuUrunuOlustur($firma, $sube, 'Tost', 90, 10);

        $response = $this->postJson('/restoran/qr-menu/'.$firma->firma_kodu.'/masalar/'.$masa->qr_siparis_kodu.'/siparis', [
            'menu_urunu_id' => $urun->id,
            'miktar' => 2,
            'mutfak_notu' => 'Ketcap olmasin',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('kalem.urun_adi', 'Tost')
            ->assertJsonPath('kalem.toplam_tutar', '198.00')
            ->assertJsonPath('adisyon.genel_toplam', '198.00');

        $this->assertDatabaseHas('restoran_adisyonlari', [
            'firma_id' => $firma->id,
            'masa_id' => $masa->id,
            'siparis_tipi' => 'qr',
            'durum' => RestoranAdisyonu::DURUM_ACIK,
        ]);
        $this->assertDatabaseHas('restoran_adisyon_kalemleri', [
            'firma_id' => $firma->id,
            'urun_adi' => 'Tost',
            'mutfak_notu' => 'Ketcap olmasin',
        ]);
    }

    public function test_qr_siparis_ayni_masanin_acik_adisyonunu_kullanir(): void
    {
        $firma = $this->firmaOlustur('QR-AYNI');
        $sube = $this->subeOlustur($firma, 'MRK');
        $masa = $this->masaOlustur($firma, $sube, 'M2');
        $urun = $this->menuUrunuOlustur($firma, $sube, 'Ayran', 30);

        $this->postJson('/restoran/qr-menu/'.$firma->firma_kodu.'/masalar/'.$masa->qr_siparis_kodu.'/siparis', [
            'menu_urunu_id' => $urun->id,
        ])->assertCreated();
        $this->postJson('/restoran/qr-menu/'.$firma->firma_kodu.'/masalar/'.$masa->qr_siparis_kodu.'/siparis', [
            'menu_urunu_id' => $urun->id,
            'miktar' => 3,
        ])->assertCreated();

        $this->assertSame(1, RestoranAdisyonu::withoutGlobalScopes()
            ->where('firma_id', $firma->id)
            ->where('masa_id', $masa->id)
            ->count());
        $this->assertSame('120.00', (string) RestoranAdisyonu::withoutGlobalScopes()
            ->where('firma_id', $firma->id)
            ->where('masa_id', $masa->id)
            ->firstOrFail()
            ->genel_toplam);
    }

    public function test_qr_siparis_firma_disi_masa_tokeni_ile_erisim_vermez(): void
    {
        $firma = $this->firmaOlustur('QR-TOK-A');
        $digerFirma = $this->firmaOlustur('QR-TOK-B');
        $sube = $this->subeOlustur($firma, 'A');
        $digerSube = $this->subeOlustur($digerFirma, 'B');
        $digerMasa = $this->masaOlustur($digerFirma, $digerSube, 'D1');
        $urun = $this->menuUrunuOlustur($firma, $sube, 'Kahve', 50);

        $this->postJson('/restoran/qr-menu/'.$firma->firma_kodu.'/masalar/'.$digerMasa->qr_siparis_kodu.'/siparis', [
            'menu_urunu_id' => $urun->id,
        ])->assertNotFound();
    }

    public function test_qr_siparis_miktar_ve_mutfak_notu_limitlerini_uygular(): void
    {
        $firma = $this->firmaOlustur('QR-LIMIT');
        $sube = $this->subeOlustur($firma, 'MRK');
        $masa = $this->masaOlustur($firma, $sube, 'L1');
        $urun = $this->menuUrunuOlustur($firma, $sube, 'Limit Urunu', 50);

        $this->postJson('/restoran/qr-menu/'.$firma->firma_kodu.'/masalar/'.$masa->qr_siparis_kodu.'/siparis', [
            'menu_urunu_id' => $urun->id,
            'miktar' => 21,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['miktar']);

        $this->postJson('/restoran/qr-menu/'.$firma->firma_kodu.'/masalar/'.$masa->qr_siparis_kodu.'/siparis', [
            'menu_urunu_id' => $urun->id,
            'miktar' => 1,
            'mutfak_notu' => str_repeat('x', 301),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mutfak_notu']);

        $this->assertSame(0, RestoranAdisyonu::withoutGlobalScopes()->where('firma_id', $firma->id)->count());
    }

    public function test_qr_aktif_adisyon_bos_masada_null_doner(): void
    {
        $firma = $this->firmaOlustur('QR-BOS');
        $sube = $this->subeOlustur($firma, 'MRK');
        $masa = $this->masaOlustur($firma, $sube, 'BOS1');

        $this->getJson('/restoran/qr-menu/'.$firma->firma_kodu.'/masalar/'.$masa->qr_siparis_kodu.'/adisyon')
            ->assertOk()
            ->assertJsonPath('adisyon', null)
            ->assertJsonCount(0, 'kalemler');
    }

    public function test_qr_aktif_adisyon_kalemleriyle_doner(): void
    {
        $firma = $this->firmaOlustur('QR-AKTIF');
        $sube = $this->subeOlustur($firma, 'MRK');
        $masa = $this->masaOlustur($firma, $sube, 'AKT1');
        $urun = $this->menuUrunuOlustur($firma, $sube, 'Pizza', 150, 10);

        $this->postJson('/restoran/qr-menu/'.$firma->firma_kodu.'/masalar/'.$masa->qr_siparis_kodu.'/siparis', [
            'menu_urunu_id' => $urun->id,
            'miktar' => 1,
        ])->assertCreated();

        $this->getJson('/restoran/qr-menu/'.$firma->firma_kodu.'/masalar/'.$masa->qr_siparis_kodu.'/adisyon')
            ->assertOk()
            ->assertJsonPath('adisyon.genel_toplam', '165.00')
            ->assertJsonPath('kalemler.0.urun_adi', 'Pizza')
            ->assertJsonPath('kalemler.0.toplam_tutar', '165.00');
    }

    public function test_qr_masa_menusu_masa_bilgisi_sube_menusu_ve_aktif_adisyon_dondurur(): void
    {
        $firma = $this->firmaOlustur('QR-MASA');
        $sube = $this->subeOlustur($firma, 'MRK');
        $digerSube = $this->subeOlustur($firma, 'DIG');
        $masa = $this->masaOlustur($firma, $sube, 'M3');
        $urun = $this->menuUrunuOlustur($firma, $sube, 'Lahmacun', 100, 10);
        $this->menuUrunuOlustur($firma, $digerSube, 'Diger Sube Urunu', 50);

        $this->postJson('/restoran/qr-menu/'.$firma->firma_kodu.'/masalar/'.$masa->qr_siparis_kodu.'/siparis', [
            'menu_urunu_id' => $urun->id,
            'miktar' => 2,
        ])->assertCreated();

        $this->getJson('/restoran/qr-menu/'.$firma->firma_kodu.'/masalar/'.$masa->qr_siparis_kodu)
            ->assertOk()
            ->assertJsonPath('firma.id', $firma->id)
            ->assertJsonPath('masa.id', $masa->id)
            ->assertJsonPath('masa.sube_id', $sube->id)
            ->assertJsonPath('kategoriler.0.urunler.0.ad', 'Lahmacun')
            ->assertJsonPath('adisyon.genel_toplam', '220.00')
            ->assertJsonPath('kalemler.0.urun_adi', 'Lahmacun')
            ->assertJsonMissing(['ad' => 'Diger Sube Urunu']);
    }

    public function test_qr_masa_menusu_firma_disi_masa_tokenini_gostermez(): void
    {
        $firma = $this->firmaOlustur('QR-MENU-A');
        $digerFirma = $this->firmaOlustur('QR-MENU-B');
        $digerSube = $this->subeOlustur($digerFirma, 'B');
        $digerMasa = $this->masaOlustur($digerFirma, $digerSube, 'B1');

        $this->getJson('/restoran/qr-menu/'.$firma->firma_kodu.'/masalar/'.$digerMasa->qr_siparis_kodu)
            ->assertNotFound();
    }

    public function test_qr_siparis_yeni_kalemi_iptal_eder_ve_toplami_gunceller(): void
    {
        $firma = $this->firmaOlustur('QR-IPTAL');
        $sube = $this->subeOlustur($firma, 'MRK');
        $masa = $this->masaOlustur($firma, $sube, 'IP1');
        $urun = $this->menuUrunuOlustur($firma, $sube, 'Salata', 80, 10);

        $ekleResponse = $this->postJson('/restoran/qr-menu/'.$firma->firma_kodu.'/masalar/'.$masa->qr_siparis_kodu.'/siparis', [
            'menu_urunu_id' => $urun->id,
            'miktar' => 2,
        ])->assertCreated();

        $kalemId = $ekleResponse->json('kalem.id');

        $this->deleteJson('/restoran/qr-menu/'.$firma->firma_kodu.'/masalar/'.$masa->qr_siparis_kodu.'/kalemler/'.$kalemId)
            ->assertOk()
            ->assertJsonPath('adisyon.genel_toplam', '0.00')
            ->assertJsonCount(0, 'kalemler');

        $this->assertDatabaseHas('restoran_adisyon_kalemleri', [
            'id' => $kalemId,
            'durum' => RestoranAdisyonKalemi::DURUM_IPTAL,
        ]);
    }

    public function test_qr_siparis_mutfaga_alinmis_kalemi_iptal_etmez(): void
    {
        $firma = $this->firmaOlustur('QR-IPTAL-RED');
        $sube = $this->subeOlustur($firma, 'MRK');
        $masa = $this->masaOlustur($firma, $sube, 'IR1');
        $urun = $this->menuUrunuOlustur($firma, $sube, 'Corba', 70);

        $ekleResponse = $this->postJson('/restoran/qr-menu/'.$firma->firma_kodu.'/masalar/'.$masa->qr_siparis_kodu.'/siparis', [
            'menu_urunu_id' => $urun->id,
        ])->assertCreated();
        $kalemId = $ekleResponse->json('kalem.id');

        RestoranAdisyonKalemi::withoutGlobalScopes()
            ->whereKey($kalemId)
            ->update(['durum' => RestoranAdisyonKalemi::DURUM_HAZIRLANIYOR]);

        $this->deleteJson('/restoran/qr-menu/'.$firma->firma_kodu.'/masalar/'.$masa->qr_siparis_kodu.'/kalemler/'.$kalemId)
            ->assertUnprocessable();

        $this->assertDatabaseHas('restoran_adisyon_kalemleri', [
            'id' => $kalemId,
            'durum' => RestoranAdisyonKalemi::DURUM_HAZIRLANIYOR,
        ]);
    }

    private function firmaOlustur(string $kod, string $durum = Firma::DURUM_AKTIF, bool $onaylandiMi = true): Firma
    {
        return Firma::query()->create([
            'ad' => 'Test '.$kod,
            'kisa_ad' => $kod,
            'firma_kodu' => $kod.'-'.uniqid(),
            'durum' => $durum,
            'onaylandi_mi' => $onaylandiMi,
        ]);
    }

    private function subeOlustur(Firma $firma, string $kod): Sube
    {
        return Sube::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad' => 'Sube '.$kod,
            'kod' => $kod,
            'aktif_mi' => true,
        ]);
    }

    private function masaOlustur(Firma $firma, Sube $sube, string $kod): RestoranMasasi
    {
        return RestoranMasasi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'ad' => 'Masa '.$kod,
            'kod' => $kod,
            'aktif_mi' => true,
        ]);
    }

    private function menuUrunuOlustur(Firma $firma, Sube $sube, string $ad, float $fiyat, float $kdvOrani = 0): RestoranMenuUrunu
    {
        $kategori = RestoranMenuKategorisi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'ad' => 'Menu '.$sube->kod,
        ]);

        return RestoranMenuUrunu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kategori_id' => $kategori->id,
            'ad' => $ad,
            'fiyat' => $fiyat,
            'kdv_orani' => $kdvOrani,
        ]);
    }
}
