<?php

namespace Tests\Feature\PersonelTakip;

use App\Models\Firma;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelMaasDonemi;
use App\Models\Personel\PersonelMaasHareketi;
use App\Models\Sube;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PersonelTakipMaasKuralTest extends TestCase
{
    use RefreshDatabase;

    public function test_maas_donemi_ad_yil_ay_ve_para_birimi_varsayilanlarini_hazirlar(): void
    {
        $firma = $this->firmaOlustur('MDV');

        $donem = PersonelMaasDonemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'baslangic_tarihi' => '2026-05-01',
            'bitis_tarihi' => '2026-05-31',
            'durum' => 'taslak',
        ]);

        $this->assertSame(2026, $donem->donem_yil);
        $this->assertSame(5, $donem->donem_ay);
        $this->assertSame('2026-05 Maaş Dönemi', $donem->ad);
        $this->assertSame('TRY', $donem->para_birimi);
    }

    public function test_maas_donemi_bitis_baslangictan_once_olamaz(): void
    {
        $firma = $this->firmaOlustur('MDB');

        $this->expectException(ValidationException::class);

        PersonelMaasDonemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'baslangic_tarihi' => '2026-05-31',
            'bitis_tarihi' => '2026-05-01',
            'durum' => 'taslak',
        ]);
    }

    public function test_maas_hareketi_net_ve_kalan_tutari_hesaplar(): void
    {
        $firma = $this->firmaOlustur('MHH');
        $personel = $this->personelOlustur($firma);
        $donem = $this->donemOlustur($firma);

        $hareket = PersonelMaasHareketi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'maas_donemi_id' => $donem->id,
            'personel_id' => $personel->id,
            'brut_tutar' => 12000,
            'fazla_mesai_tutari' => 1500,
            'prim_tutari' => 500,
            'ek_odeme_tutari' => 250,
            'avans_kesintisi' => 1000,
            'devamsizlik_kesintisi' => 250,
            'diger_kesinti' => 0,
            'odenen_tutar' => 3000,
            'durum' => 'taslak',
        ]);

        $this->assertSame('13000.00', $hareket->net_tutar);
        $this->assertSame('10000.00', $hareket->kalan_tutar);
    }

    public function test_maas_hareketi_farkli_firmanin_personeline_yazilamaz(): void
    {
        $firmaA = $this->firmaOlustur('MHA');
        $firmaB = $this->firmaOlustur('MHB');
        $donem = $this->donemOlustur($firmaA);
        $personelB = $this->personelOlustur($firmaB);

        $this->expectException(ValidationException::class);

        PersonelMaasHareketi::withoutGlobalScopes()->create([
            'firma_id' => $firmaA->id,
            'maas_donemi_id' => $donem->id,
            'personel_id' => $personelB->id,
            'brut_tutar' => 10000,
            'durum' => 'taslak',
        ]);
    }

    public function test_maas_hareketinde_odenen_tutar_net_tutari_asamaz(): void
    {
        $firma = $this->firmaOlustur('MHO');
        $personel = $this->personelOlustur($firma);
        $donem = $this->donemOlustur($firma);

        $this->expectException(ValidationException::class);

        PersonelMaasHareketi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'maas_donemi_id' => $donem->id,
            'personel_id' => $personel->id,
            'brut_tutar' => 1000,
            'odenen_tutar' => 1500,
            'durum' => 'taslak',
        ]);
    }

    public function test_sube_bazli_maas_donemi_personel_subesiyle_uyumlu_olmalidir(): void
    {
        $firma = $this->firmaOlustur('MHS');
        $subeA = $this->subeOlustur($firma, 'A');
        $subeB = $this->subeOlustur($firma, 'B');
        $personel = $this->personelOlustur($firma, $subeB);
        $donem = $this->donemOlustur($firma, $subeA);

        $this->expectException(ValidationException::class);

        PersonelMaasHareketi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'maas_donemi_id' => $donem->id,
            'personel_id' => $personel->id,
            'brut_tutar' => 1000,
            'durum' => 'taslak',
        ]);
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

    private function personelOlustur(Firma $firma, ?Sube $sube = null): Personel
    {
        return Personel::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube?->id,
            'ad_soyad' => 'Maaş Personeli',
            'calisma_tipi' => 'tam_zamanli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 10000,
            'durum' => Personel::DURUM_AKTIF,
        ]);
    }

    private function donemOlustur(Firma $firma, ?Sube $sube = null): PersonelMaasDonemi
    {
        return PersonelMaasDonemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube?->id,
            'baslangic_tarihi' => '2026-05-01',
            'bitis_tarihi' => '2026-05-31',
            'durum' => 'taslak',
        ]);
    }
}
