<?php

namespace Tests\Feature\Restoran;

use App\Models\Firma;
use App\Models\Muhasebe\StokKarti;
use App\Models\Personel\Personel;
use App\Models\Restoran\RestoranAdisyonKalemi;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Restoran\RestoranMasasi;
use App\Models\Restoran\RestoranSalonu;
use App\Models\Sube;
use App\Models\User;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RestoranCekirdekKuralTest extends TestCase
{
    use RefreshDatabase;

    public function test_restoran_masalari_aktif_firma_ile_scope_edilir(): void
    {
        $firmaA = $this->firmaOlustur('RSA');
        $firmaB = $this->firmaOlustur('RSB');

        RestoranMasasi::withoutGlobalScopes()->create([
            'firma_id' => $firmaA->id,
            'ad' => 'A1',
            'kod' => 'A1',
        ]);
        RestoranMasasi::withoutGlobalScopes()->create([
            'firma_id' => $firmaB->id,
            'ad' => 'B1',
            'kod' => 'B1',
        ]);

        $this->actingAs(User::factory()->create());
        app(TenantContextService::class)->firmaAyarla($firmaA);

        $this->assertSame(['A1'], RestoranMasasi::query()->pluck('ad')->all());

        app(TenantContextService::class)->firmaAyarla($firmaB);

        $this->assertSame(['B1'], RestoranMasasi::query()->pluck('ad')->all());
    }

    public function test_masa_salon_ve_sube_ayni_firmada_olmalidir(): void
    {
        $firma = $this->firmaOlustur('RSM');
        $digerFirma = $this->firmaOlustur('RSD');
        $sube = $this->subeOlustur($firma, 'MRK');
        $digerSube = $this->subeOlustur($digerFirma, 'DG');
        $salon = RestoranSalonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'ad' => 'Salon',
            'kod' => 'SALON',
        ]);

        $this->expectException(ValidationException::class);

        RestoranMasasi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $digerSube->id,
            'salon_id' => $salon->id,
            'ad' => 'Masa',
            'kod' => 'MASA',
        ]);
    }

    public function test_acik_adisyon_masayi_dolu_yapar_ve_ikinci_acik_adisyonu_engeller(): void
    {
        $firma = $this->firmaOlustur('RSAK');
        $sube = $this->subeOlustur($firma, 'MRK');
        $masa = RestoranMasasi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'ad' => 'Masa 1',
            'kod' => 'M1',
        ]);
        $garson = $this->personelOlustur($firma, $sube, 'Garson');

        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'masa_id' => $masa->id,
            'garson_personel_id' => $garson->id,
        ]);

        $this->assertSame(RestoranMasasi::DURUM_DOLU, $masa->refresh()->durum);
        $this->assertSame($sube->id, $adisyon->refresh()->sube_id);
        $this->assertStringStartsWith('AD-', $adisyon->adisyon_no);

        $this->expectException(ValidationException::class);

        RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'masa_id' => $masa->id,
            'garson_personel_id' => $garson->id,
        ]);
    }

    public function test_adisyon_kalemi_stoktan_bilgileri_alir_ve_toplamlari_gunceller(): void
    {
        $firma = $this->firmaOlustur('RSK');
        $sube = $this->subeOlustur($firma, 'MRK');
        $personel = $this->personelOlustur($firma, $sube, 'Mutfak');
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
        ]);
        $stok = StokKarti::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kod' => 'YMK-1',
            'ad' => 'Test Yemek',
            'tur' => 'hizmet',
            'birim' => 'AD',
            'satis_fiyati' => 100,
            'kdv_orani' => 10,
            'durum' => 'aktif',
        ]);

        $kalem = RestoranAdisyonKalemi::withoutGlobalScopes()->create([
            'adisyon_id' => $adisyon->id,
            'stok_karti_id' => $stok->id,
            'hazirlayan_personel_id' => $personel->id,
            'miktar' => 2,
            'iskonto_tutari' => 20,
        ]);

        $this->assertSame((int) $firma->id, (int) $kalem->firma_id);
        $this->assertSame('Test Yemek', $kalem->urun_adi);
        $this->assertSame('200.00', (string) $kalem->ara_tutar);
        $this->assertSame('18.00', (string) $kalem->kdv_tutari);
        $this->assertSame('198.00', (string) $kalem->toplam_tutar);
        $this->assertSame('198.00', (string) $adisyon->refresh()->genel_toplam);
    }

    public function test_ikram_kalemi_tahsilata_yansimaz_ve_ikram_toplamina_yazilir(): void
    {
        $firma = $this->firmaOlustur('RSIK');
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
        ]);

        $kalem = RestoranAdisyonKalemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_id' => $adisyon->id,
            'urun_adi' => 'Ikram Cay',
            'miktar' => 2,
            'birim_fiyat' => 20,
            'kdv_orani' => 10,
            'ikram_mi' => true,
        ]);

        $this->assertSame('44.00', (string) $kalem->ikram_tutari);
        $this->assertSame('0.00', (string) $kalem->toplam_tutar);
        $this->assertSame('44.00', (string) $adisyon->refresh()->ikram_toplam);
        $this->assertSame('0.00', (string) $adisyon->genel_toplam);
    }

    public function test_servis_ucreti_genel_toplama_eklenir(): void
    {
        $firma = $this->firmaOlustur('RSSU');
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'servis_ucreti' => 15,
        ]);

        RestoranAdisyonKalemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_id' => $adisyon->id,
            'urun_adi' => 'Yemek',
            'miktar' => 1,
            'birim_fiyat' => 100,
            'kdv_orani' => 10,
        ]);

        $this->assertSame('125.00', (string) $adisyon->refresh()->genel_toplam);
    }

    public function test_masa_qr_siparis_kodu_uretilir_ve_yenilenir(): void
    {
        $firma = $this->firmaOlustur('RSQR');
        $sube = $this->subeOlustur($firma, 'MRK');

        $masa = RestoranMasasi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'ad' => 'Masa QR',
            'kod' => 'QR1',
        ]);
        $ilkKod = $masa->qr_siparis_kodu;

        $masa->qrSiparisKodunuYenile();

        $this->assertNotEmpty($ilkKod);
        $this->assertNotSame($ilkKod, $masa->refresh()->qr_siparis_kodu);
        $this->assertSame(40, strlen((string) $masa->qr_siparis_kodu));
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

    private function personelOlustur(Firma $firma, Sube $sube, string $ad): Personel
    {
        return Personel::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'ad_soyad' => $ad,
            'calisma_tipi' => 'tam_zamanli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 0,
            'durum' => Personel::DURUM_AKTIF,
        ]);
    }
}
