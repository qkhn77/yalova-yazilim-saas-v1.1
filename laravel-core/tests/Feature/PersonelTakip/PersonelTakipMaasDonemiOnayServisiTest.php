<?php

namespace Tests\Feature\PersonelTakip;

use App\Models\Firma;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelMaasDonemi;
use App\Models\Personel\PersonelMaasHareketi;
use App\Models\Personel\PersonelMaasOdemeKaydi;
use App\Models\User;
use App\Services\PersonelTakip\PersonelMaasDonemiOnayServisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PersonelTakipMaasDonemiOnayServisiTest extends TestCase
{
    use RefreshDatabase;

    public function test_maas_donemi_onaylanir_ve_hareketler_onaylanir(): void
    {
        $firma = $this->firmaOlustur('MDO');
        $donem = $this->donemOlustur($firma);
        $this->hareketOlustur($firma, $donem, 5000);
        $onaylayan = User::factory()->create();

        $sonuc = app(PersonelMaasDonemiOnayServisi::class)->onayla($firma->id, $donem->id, $onaylayan->id);

        $this->assertSame('onaylandi', $sonuc->durum);
        $this->assertSame($onaylayan->id, $sonuc->onaylayan_id);
        $this->assertNotNull($sonuc->onay_at);
        $this->assertSame('onaylandi', PersonelMaasHareketi::withoutGlobalScopes()->firstOrFail()->durum);
    }

    public function test_hareketi_olmayan_maas_donemi_onaylanamaz(): void
    {
        $firma = $this->firmaOlustur('MDB');
        $donem = $this->donemOlustur($firma);

        $this->expectException(ValidationException::class);

        app(PersonelMaasDonemiOnayServisi::class)->onayla($firma->id, $donem->id, null);
    }

    public function test_farkli_firma_maas_donemi_onaylanamaz(): void
    {
        $firmaA = $this->firmaOlustur('MDA');
        $firmaB = $this->firmaOlustur('MDC');
        $donemB = $this->donemOlustur($firmaB);
        $this->hareketOlustur($firmaB, $donemB, 5000);

        $this->expectException(ValidationException::class);

        app(PersonelMaasDonemiOnayServisi::class)->onayla($firmaA->id, $donemB->id, null);
    }

    public function test_onayli_donem_tum_hareketler_odenince_odendi_olur(): void
    {
        $firma = $this->firmaOlustur('MDP');
        $donem = $this->donemOlustur($firma);
        $hareket = $this->hareketOlustur($firma, $donem, 5000);
        $kasa = $this->kasaOlustur($firma);

        app(PersonelMaasDonemiOnayServisi::class)->onayla($firma->id, $donem->id, null);

        PersonelMaasOdemeKaydi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'maas_hareketi_id' => $hareket->id,
            'tarih' => '2026-05-31',
            'tutar' => 5000,
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
        ]);

        $this->assertSame('odendi', $donem->refresh()->durum);
    }

    public function test_odenmis_maas_donemi_tekrar_onaylanamaz(): void
    {
        $firma = $this->firmaOlustur('MDD');
        $donem = $this->donemOlustur($firma);
        $this->hareketOlustur($firma, $donem, 5000);
        $donem->forceFill(['durum' => 'odendi'])->save();

        $this->expectException(ValidationException::class);

        app(PersonelMaasDonemiOnayServisi::class)->onayla($firma->id, $donem->id, null);
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

    private function donemOlustur(Firma $firma): PersonelMaasDonemi
    {
        return PersonelMaasDonemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'baslangic_tarihi' => '2026-05-01',
            'bitis_tarihi' => '2026-05-31',
            'durum' => 'hesaplandi',
        ]);
    }

    private function hareketOlustur(Firma $firma, PersonelMaasDonemi $donem, int $netTutar): PersonelMaasHareketi
    {
        $personel = Personel::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad_soyad' => 'Onay Maas Personeli',
            'calisma_tipi' => 'tam_zamanli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => $netTutar,
            'durum' => Personel::DURUM_AKTIF,
        ]);

        return PersonelMaasHareketi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'maas_donemi_id' => $donem->id,
            'personel_id' => $personel->id,
            'brut_tutar' => $netTutar,
            'durum' => 'taslak',
        ]);
    }

    private function kasaOlustur(Firma $firma): KasaHesabi
    {
        return KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'KASA-'.uniqid(),
            'ad' => 'Maas Onay Kasasi',
            'para_birimi' => 'TRY',
        ]);
    }
}
