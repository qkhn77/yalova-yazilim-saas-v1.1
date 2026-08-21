<?php

namespace Tests\Feature\PersonelTakip;

use App\Models\Firma;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelAvansi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PersonelTakipAvansKuralTest extends TestCase
{
    use RefreshDatabase;

    public function test_avans_kalan_tutar_ve_alias_alanlari_hazirlar(): void
    {
        $firma = $this->firmaOlustur('AVK');
        $personel = $this->personelOlustur($firma);
        $kasa = $this->kasaOlustur($firma);

        $avans = PersonelAvansi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'tarih' => '2026-05-31',
            'tutar' => 750,
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
            'durum' => 'onaylandi',
        ]);

        $this->assertSame('750.00', $avans->kalan_tutar);
        $this->assertSame('TRY', $avans->para_birimi);
        $this->assertSame('onaylandi', $avans->onay_durumu);
        $this->assertSame('bekliyor', $avans->mahsup_durumu);
        $this->assertSame('kasa', $avans->odeme_kaynagi);
        $this->assertSame($kasa->id, $avans->kasa_hesabi_id);
    }

    public function test_maastan_dusulen_avansin_kalani_sifirlanir(): void
    {
        $firma = $this->firmaOlustur('AVM');
        $personel = $this->personelOlustur($firma);

        $avans = PersonelAvansi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'tarih' => '2026-05-31',
            'tutar' => 500,
            'durum' => 'taslak',
            'maastan_dusuldu_mu' => true,
        ]);

        $this->assertSame('0.00', $avans->kalan_tutar);
        $this->assertSame('mahsup_edildi', $avans->mahsup_durumu);
    }

    public function test_avans_farkli_firmanin_personeline_yazilamaz(): void
    {
        $firmaA = $this->firmaOlustur('AVA');
        $firmaB = $this->firmaOlustur('AVB');
        $personelB = $this->personelOlustur($firmaB);

        $this->expectException(ValidationException::class);

        PersonelAvansi::withoutGlobalScopes()->create([
            'firma_id' => $firmaA->id,
            'personel_id' => $personelB->id,
            'tarih' => '2026-05-31',
            'tutar' => 500,
            'durum' => 'taslak',
        ]);
    }

    public function test_onayli_kasa_avansi_icin_firma_hesabi_secili_olmalidir(): void
    {
        $firmaA = $this->firmaOlustur('AH1');
        $firmaB = $this->firmaOlustur('AH2');
        $personel = $this->personelOlustur($firmaA);
        $baskaFirmaKasasi = $this->kasaOlustur($firmaB);

        $this->expectException(ValidationException::class);

        PersonelAvansi::withoutGlobalScopes()->create([
            'firma_id' => $firmaA->id,
            'personel_id' => $personel->id,
            'tarih' => '2026-05-31',
            'tutar' => 500,
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $baskaFirmaKasasi->id,
            'durum' => 'onaylandi',
        ]);
    }

    public function test_avans_odeme_hesabi_para_birimi_ile_uyumlu_olmalidir(): void
    {
        $firma = $this->firmaOlustur('AHP');
        $personel = $this->personelOlustur($firma);
        $usdKasasi = $this->kasaOlustur($firma, 'USD');

        $this->expectException(ValidationException::class);

        PersonelAvansi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'tarih' => '2026-05-31',
            'tutar' => 500,
            'para_birimi' => 'TRY',
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $usdKasasi->id,
            'durum' => 'onaylandi',
        ]);
    }

    public function test_avans_tutari_sifirdan_buyuk_olmalidir(): void
    {
        $firma = $this->firmaOlustur('AVT');
        $personel = $this->personelOlustur($firma);

        $this->expectException(ValidationException::class);

        PersonelAvansi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'tarih' => '2026-05-31',
            'tutar' => 0,
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

    private function personelOlustur(Firma $firma): Personel
    {
        return Personel::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad_soyad' => 'Avans Personeli',
            'calisma_tipi' => 'tam_zamanli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 10000,
            'durum' => Personel::DURUM_AKTIF,
        ]);
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
