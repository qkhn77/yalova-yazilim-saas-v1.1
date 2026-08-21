<?php

namespace Tests\Feature\Restoran;

use App\Models\Firma;
use App\Models\Personel\Personel;
use App\Models\Restoran\RestoranAdisyonKalemi;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Sube;
use App\Services\Restoran\RestoranMutfakServisi;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RestoranMutfakServisiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mutfak_durumlari_sirali_ilerler_ve_hazirlayan_personel_baglanir(): void
    {
        $firma = $this->firmaOlustur('RMF');
        $sube = $this->subeOlustur($firma, 'MRK');
        $personel = $this->personelOlustur($firma, $sube);
        $adisyon = $this->adisyonOlustur($firma, $sube);
        $kalem = $this->kalemOlustur($firma, $adisyon, 'Lahmacun', 2, 80, 10);

        $servis = app(RestoranMutfakServisi::class);

        $kalem = $servis->hazirlamayaAl($kalem, $personel->id);
        $this->assertSame(RestoranAdisyonKalemi::DURUM_HAZIRLANIYOR, $kalem->durum);
        $this->assertSame((int) $personel->id, (int) $kalem->hazirlayan_personel_id);

        $kalem = $servis->hazirIsaretle($kalem);
        $this->assertSame(RestoranAdisyonKalemi::DURUM_HAZIR, $kalem->durum);

        $kalem = $servis->servisEdildiIsaretle($kalem);
        $this->assertSame(RestoranAdisyonKalemi::DURUM_SERVIS_EDILDI, $kalem->durum);
    }

    public function test_gecersiz_mutfak_durum_gecisi_engellenir(): void
    {
        $firma = $this->firmaOlustur('RMG');
        $sube = $this->subeOlustur($firma, 'MRK');
        $adisyon = $this->adisyonOlustur($firma, $sube);
        $kalem = $this->kalemOlustur($firma, $adisyon, 'Pide', 1, 120, 10);

        $this->expectException(ValidationException::class);

        app(RestoranMutfakServisi::class)->servisEdildiIsaretle($kalem);
    }

    public function test_kalem_iptali_adisyon_toplamini_gunceller(): void
    {
        $firma = $this->firmaOlustur('RMI');
        $sube = $this->subeOlustur($firma, 'MRK');
        $adisyon = $this->adisyonOlustur($firma, $sube);
        $kalemA = $this->kalemOlustur($firma, $adisyon, 'Köfte', 1, 100, 10);
        $kalemB = $this->kalemOlustur($firma, $adisyon, 'Ayran', 2, 20, 10);

        $this->assertSame('154.00', (string) $adisyon->refresh()->genel_toplam);

        $iptalKalem = app(RestoranMutfakServisi::class)->iptalEt($kalemB, 'Yanlış ürün');

        $this->assertSame(RestoranAdisyonKalemi::DURUM_IPTAL, $iptalKalem->durum);
        $this->assertStringContainsString('Yanlış ürün', (string) $iptalKalem->mutfak_notu);
        $this->assertSame('110.00', (string) $adisyon->refresh()->genel_toplam);
        $this->assertSame(RestoranAdisyonKalemi::DURUM_YENI, $kalemA->refresh()->durum);
    }

    public function test_kapali_adisyonda_mutfak_durumu_degistirilemez(): void
    {
        $firma = $this->firmaOlustur('RMK');
        $sube = $this->subeOlustur($firma, 'MRK');
        $adisyon = $this->adisyonOlustur($firma, $sube);
        $kalem = $this->kalemOlustur($firma, $adisyon, 'Tatlı', 1, 70, 10);
        $adisyon->forceFill([
            'durum' => RestoranAdisyonu::DURUM_KAPANDI,
            'kapanis_at' => now(),
        ])->save();

        $this->expectException(ValidationException::class);

        app(RestoranMutfakServisi::class)->hazirlamayaAl($kalem);
    }

    public function test_aktif_firma_disindaki_kalemde_mutfak_islemi_yapilamaz(): void
    {
        $firmaA = $this->firmaOlustur('RMA');
        $firmaB = $this->firmaOlustur('RMB');
        $subeB = $this->subeOlustur($firmaB, 'MRK');
        $adisyonB = $this->adisyonOlustur($firmaB, $subeB);
        $kalemB = $this->kalemOlustur($firmaB, $adisyonB, 'Firma B Ürünü', 1, 100, 10);

        app(TenantContextService::class)->firmaAyarla($firmaA);

        try {
            app(RestoranMutfakServisi::class)->hazirlamayaAl($kalemB);
            $this->fail('Aktif firma dışı mutfak işlemi validasyonu bekleniyordu.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('firma_id', $exception->errors());
        }

        $this->assertSame(RestoranAdisyonKalemi::DURUM_YENI, $kalemB->refresh()->durum);
    }

    public function test_mutfak_kuyrugu_geciken_kalemleri_tenant_ve_siparis_tipine_gore_hesaplar(): void
    {
        $referans = now();
        $firma = $this->firmaOlustur('RMQ');
        $sube = $this->subeOlustur($firma, 'MRK');
        $adisyon = $this->adisyonOlustur($firma, $sube, 'paket');
        $gecikenKalem = $this->kalemOlustur($firma, $adisyon, 'Paket Döner', 1, 150, 10);
        $gecikenKalem->forceFill(['created_at' => $referans->copy()->subMinutes(20)])->saveQuietly();

        $masaAdisyonu = $this->adisyonOlustur($firma, $sube, 'masa');
        $masaKalemi = $this->kalemOlustur($firma, $masaAdisyonu, 'Masa Çorba', 1, 80, 10);
        $masaKalemi->forceFill(['created_at' => $referans->copy()->subMinutes(30)])->saveQuietly();

        $kapaliAdisyon = $this->adisyonOlustur($firma, $sube, 'paket');
        $kapaliKalem = $this->kalemOlustur($firma, $kapaliAdisyon, 'Kapalı Kalem', 1, 90, 10);
        $kapaliKalem->forceFill(['created_at' => $referans->copy()->subMinutes(40)])->saveQuietly();
        $kapaliAdisyon->forceFill(['durum' => RestoranAdisyonu::DURUM_KAPANDI])->saveQuietly();

        $digerFirma = $this->firmaOlustur('RMO');
        $digerSube = $this->subeOlustur($digerFirma, 'MRK');
        $digerAdisyon = $this->adisyonOlustur($digerFirma, $digerSube, 'paket');
        $digerKalem = $this->kalemOlustur($digerFirma, $digerAdisyon, 'Başka Firma', 1, 100, 10);
        $digerKalem->forceFill(['created_at' => $referans->copy()->subMinutes(50)])->saveQuietly();

        $servis = app(RestoranMutfakServisi::class);
        $kuyruk = $servis->mutfakKuyrugu((int) $firma->id, 'aktif', 'paket', 100, $referans, 15);
        $ozet = $servis->durumOzeti((int) $firma->id, $referans, 15);

        $this->assertCount(1, $kuyruk);
        $this->assertSame((int) $gecikenKalem->id, (int) $kuyruk->first()->id);
        $this->assertSame(20, (int) $kuyruk->first()->getAttribute('bekleme_dakika'));
        $this->assertTrue((bool) $kuyruk->first()->getAttribute('gecikti_mi'));
        $this->assertSame(2, $ozet[RestoranAdisyonKalemi::DURUM_YENI]);
        $this->assertSame(2, $ozet['geciken']);
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
            'ad' => 'Şube '.$kod,
            'kod' => $kod,
            'aktif_mi' => true,
        ]);
    }

    private function personelOlustur(Firma $firma, Sube $sube): Personel
    {
        return Personel::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'ad_soyad' => 'Mutfak Personeli',
            'calisma_tipi' => 'tam_zamanli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 0,
            'durum' => Personel::DURUM_AKTIF,
        ]);
    }

    private function adisyonOlustur(Firma $firma, Sube $sube, string $siparisTipi = 'masa'): RestoranAdisyonu
    {
        return RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'siparis_tipi' => $siparisTipi,
        ]);
    }

    private function kalemOlustur(
        Firma $firma,
        RestoranAdisyonu $adisyon,
        string $urunAdi,
        float $miktar,
        float $birimFiyat,
        float $kdvOrani
    ): RestoranAdisyonKalemi {
        return RestoranAdisyonKalemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_id' => $adisyon->id,
            'urun_adi' => $urunAdi,
            'miktar' => $miktar,
            'birim_fiyat' => $birimFiyat,
            'kdv_orani' => $kdvOrani,
        ]);
    }
}
