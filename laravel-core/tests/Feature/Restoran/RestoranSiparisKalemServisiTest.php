<?php

namespace Tests\Feature\Restoran;

use App\Models\Firma;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Restoran\RestoranMenuKategorisi;
use App\Models\Restoran\RestoranMenuUrunu;
use App\Models\Sube;
use App\Services\Restoran\RestoranSiparisKalemServisi;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RestoranSiparisKalemServisiTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_urunu_adisyona_kalem_olarak_eklenir_ve_toplam_guncellenir(): void
    {
        $firma = $this->firmaOlustur('RSK-EKLE');
        $sube = $this->subeOlustur($firma, 'MRK');
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
        ]);
        $urun = $this->menuUrunuOlustur($firma, $sube, 'Burger', 120, 10);

        $kalem = app(RestoranSiparisKalemServisi::class)->menuUrunuEkle($adisyon, $urun, 2, 'Az pismis');

        $this->assertSame((int) $firma->id, (int) $kalem->firma_id);
        $this->assertSame((int) $adisyon->id, (int) $kalem->adisyon_id);
        $this->assertSame((int) $urun->id, (int) $kalem->menu_urunu_id);
        $this->assertSame('Burger', $kalem->urun_adi);
        $this->assertSame('240.00', (string) $kalem->ara_tutar);
        $this->assertSame('24.00', (string) $kalem->kdv_tutari);
        $this->assertSame('264.00', (string) $adisyon->refresh()->genel_toplam);
    }

    public function test_firma_disi_menu_urunu_adisyona_eklenemez(): void
    {
        $firma = $this->firmaOlustur('RSK-A');
        $digerFirma = $this->firmaOlustur('RSK-B');
        $sube = $this->subeOlustur($firma, 'A');
        $digerSube = $this->subeOlustur($digerFirma, 'B');
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
        ]);
        $urun = $this->menuUrunuOlustur($digerFirma, $digerSube, 'Diger Urun', 100);

        $this->expectException(ValidationException::class);

        app(RestoranSiparisKalemServisi::class)->menuUrunuEkle($adisyon, $urun);
    }

    public function test_aktif_firma_disindaki_adisyona_menu_urunu_eklenemez(): void
    {
        $firmaA = $this->firmaOlustur('RSK-AKTIF-A');
        $firmaB = $this->firmaOlustur('RSK-AKTIF-B');
        $subeB = $this->subeOlustur($firmaB, 'B');
        $adisyonB = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firmaB->id,
            'sube_id' => $subeB->id,
        ]);
        $urunB = $this->menuUrunuOlustur($firmaB, $subeB, 'Firma B Menü', 100);

        app(TenantContextService::class)->firmaAyarla($firmaA);

        try {
            app(RestoranSiparisKalemServisi::class)->menuUrunuEkle($adisyonB, $urunB);
            $this->fail('Aktif firma dışı sipariş kalemi validasyonu bekleniyordu.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('firma_id', $exception->errors());
        }

        $this->assertSame('0.00', (string) $adisyonB->refresh()->genel_toplam);
    }

    public function test_kapali_adisyona_menu_urunu_eklenemez(): void
    {
        $firma = $this->firmaOlustur('RSK-KAPALI');
        $sube = $this->subeOlustur($firma, 'MRK');
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'durum' => RestoranAdisyonu::DURUM_KAPANDI,
        ]);
        $urun = $this->menuUrunuOlustur($firma, $sube, 'Corba', 80);

        $this->expectException(ValidationException::class);

        app(RestoranSiparisKalemServisi::class)->menuUrunuEkle($adisyon, $urun);
    }

    public function test_pasif_ve_stokta_olmayan_menu_urunu_eklenemez(): void
    {
        $firma = $this->firmaOlustur('RSK-PASIF');
        $sube = $this->subeOlustur($firma, 'MRK');
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
        ]);
        $urun = $this->menuUrunuOlustur($firma, $sube, 'Tukenmis Urun', 75);
        $urun->forceFill(['stokta_var_mi' => false])->save();

        $this->expectException(ValidationException::class);

        app(RestoranSiparisKalemServisi::class)->menuUrunuEkle($adisyon, $urun);
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

    private function subeOlustur(Firma $firma, string $kod): Sube
    {
        return Sube::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad' => 'Sube '.$kod,
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
