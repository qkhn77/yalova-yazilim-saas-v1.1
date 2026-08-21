<?php

namespace Tests\Feature\PersonelTakip;

use App\Models\Firma;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelAvansi;
use App\Models\Personel\PersonelMaasDonemi;
use App\Models\Personel\PersonelMaasHareketi;
use App\Models\Personel\PersonelMaasOdemeKaydi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PersonelTakipMaasOdemeKuralTest extends TestCase
{
    use RefreshDatabase;

    public function test_maas_odeme_hareketin_odenen_ve_kalan_tutarini_gunceller(): void
    {
        $firma = $this->firmaOlustur('MOH');
        $hareket = $this->maasHareketiOlustur($firma, 5000);
        $kasa = $this->kasaOlustur($firma);

        PersonelMaasOdemeKaydi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'maas_hareketi_id' => $hareket->id,
            'tarih' => '2026-05-31',
            'tutar' => 2000,
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
        ]);

        $hareket->refresh();
        $this->assertSame('2000.00', $hareket->odenen_tutar);
        $this->assertSame('3000.00', $hareket->kalan_tutar);
        $this->assertSame('taslak', $hareket->durum);
    }

    public function test_maas_odeme_tam_odeme_yapinca_hareket_odendi_olur(): void
    {
        $firma = $this->firmaOlustur('MOT');
        $hareket = $this->maasHareketiOlustur($firma, 5000);
        $kasa = $this->kasaOlustur($firma);

        PersonelMaasOdemeKaydi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'maas_hareketi_id' => $hareket->id,
            'tarih' => '2026-05-31',
            'tutar' => 5000,
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
        ]);

        $hareket->refresh();
        $this->assertSame('5000.00', $hareket->odenen_tutar);
        $this->assertSame('0.00', $hareket->kalan_tutar);
        $this->assertSame('odendi', $hareket->durum);
    }

    public function test_maas_tam_odenince_maastan_kesilen_avans_mahsup_edilir(): void
    {
        $firma = $this->firmaOlustur('MOM');
        $hareket = $this->maasHareketiOlustur($firma, 6000, [
            'avans_kesintisi' => 1000,
        ]);
        $kasa = $this->kasaOlustur($firma);
        $avans = PersonelAvansi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $hareket->personel_id,
            'tarih' => '2026-05-15',
            'tutar' => 1000,
            'kalan_tutar' => 1000,
            'durum' => 'onaylandi',
            'onay_durumu' => 'onaylandi',
        ]);

        PersonelMaasOdemeKaydi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'maas_hareketi_id' => $hareket->id,
            'tarih' => '2026-05-31',
            'tutar' => 5000,
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
        ]);

        $avans->refresh();
        $this->assertTrue((bool) $avans->maastan_dusuldu_mu);
        $this->assertSame('0.00', $avans->kalan_tutar);
        $this->assertSame('mahsup_edildi', $avans->mahsup_durumu);
    }

    public function test_maas_odeme_net_tutari_asamaz(): void
    {
        $firma = $this->firmaOlustur('MOA');
        $hareket = $this->maasHareketiOlustur($firma, 5000);

        $this->expectException(ValidationException::class);

        PersonelMaasOdemeKaydi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'maas_hareketi_id' => $hareket->id,
            'tarih' => '2026-05-31',
            'tutar' => 5001,
        ]);
    }

    public function test_maas_odeme_farkli_firma_hesabini_kullanamaz(): void
    {
        $firmaA = $this->firmaOlustur('MFA');
        $firmaB = $this->firmaOlustur('MFB');
        $hareket = $this->maasHareketiOlustur($firmaA, 5000);
        $baskaFirmaKasasi = $this->kasaOlustur($firmaB);

        $this->expectException(ValidationException::class);

        PersonelMaasOdemeKaydi::withoutGlobalScopes()->create([
            'firma_id' => $firmaA->id,
            'maas_hareketi_id' => $hareket->id,
            'tarih' => '2026-05-31',
            'tutar' => 1000,
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $baskaFirmaKasasi->id,
        ]);
    }

    public function test_maas_odeme_hesabi_para_birimi_ile_uyumlu_olmalidir(): void
    {
        $firma = $this->firmaOlustur('MOP');
        $hareket = $this->maasHareketiOlustur($firma, 5000);
        $usdKasasi = $this->kasaOlustur($firma, 'USD');

        $this->expectException(ValidationException::class);

        PersonelMaasOdemeKaydi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'maas_hareketi_id' => $hareket->id,
            'tarih' => '2026-05-31',
            'tutar' => 1000,
            'para_birimi' => 'TRY',
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $usdKasasi->id,
        ]);
    }

    public function test_personel_referansli_finans_hareketi_modul_etiketi_personel_takip_olur(): void
    {
        $firma = $this->firmaOlustur('MFE');

        $finans = FinansHareketi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'tur' => 'odeme',
            'tarih' => now(),
            'tutar' => 1000,
            'para_birimi' => 'TRY',
            'referans_turu' => 'personel_maas_odeme',
            'referans_id' => 1,
            'durum' => 'aktif',
        ]);

        $this->assertSame('Personel Takip', $finans->modul_etiketi);
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

    private function maasHareketiOlustur(Firma $firma, int $netTutar, array $ek = []): PersonelMaasHareketi
    {
        $personel = Personel::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad_soyad' => 'Ödeme Personeli',
            'calisma_tipi' => 'tam_zamanli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => $netTutar,
            'durum' => Personel::DURUM_AKTIF,
        ]);
        $donem = PersonelMaasDonemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'baslangic_tarihi' => '2026-05-01',
            'bitis_tarihi' => '2026-05-31',
            'durum' => 'taslak',
        ]);

        return PersonelMaasHareketi::withoutGlobalScopes()->create(array_merge([
            'firma_id' => $firma->id,
            'maas_donemi_id' => $donem->id,
            'personel_id' => $personel->id,
            'brut_tutar' => $netTutar,
            'durum' => 'taslak',
        ], $ek));
    }

    private function kasaOlustur(Firma $firma, string $paraBirimi = 'TRY'): KasaHesabi
    {
        return KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'KASA-'.uniqid(),
            'ad' => 'Test Kasası',
            'para_birimi' => $paraBirimi,
        ]);
    }
}
