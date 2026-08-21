<?php

namespace Tests\Feature\PersonelTakip;

use App\Models\Firma;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelAvansi;
use App\Models\Personel\PersonelIzni;
use App\Models\Personel\PersonelVardiyasi;
use App\Models\User;
use App\Services\PersonelTakip\PersonelAvansOnayServisi;
use App\Services\PersonelTakip\PersonelIzinOnayServisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PersonelTakipOnayServisleriTest extends TestCase
{
    use RefreshDatabase;

    public function test_izin_onaylanir_ve_onay_bilgileri_yazilir(): void
    {
        $firma = $this->firmaOlustur('IOS');
        $personel = $this->personelOlustur($firma);
        $onaylayan = User::factory()->create();
        $izin = $this->izinOlustur($firma, $personel);

        $sonuc = app(PersonelIzinOnayServisi::class)->onayla($firma->id, $izin->id, $onaylayan->id);

        $this->assertSame('onaylandi', $sonuc->durum);
        $this->assertSame('onaylandi', $sonuc->onay_durumu);
        $this->assertSame($onaylayan->id, $sonuc->onaylayan_id);
        $this->assertNotNull($sonuc->onay_at);
    }

    public function test_aktif_vardiya_ile_cakisan_izin_onaylanamaz(): void
    {
        $firma = $this->firmaOlustur('IOC');
        $personel = $this->personelOlustur($firma);
        $izin = $this->izinOlustur($firma, $personel);

        PersonelVardiyasi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'tarih' => '2026-06-01',
            'baslangic_at' => '2026-06-01 12:00:00',
            'bitis_at' => '2026-06-01 18:00:00',
            'durum' => 'planlandi',
        ]);

        $this->expectException(ValidationException::class);

        app(PersonelIzinOnayServisi::class)->onayla($firma->id, $izin->id, null);
    }

    public function test_avans_onaylanir_ve_reddedilir(): void
    {
        $firma = $this->firmaOlustur('AOS');
        $personel = $this->personelOlustur($firma);
        $onaylayan = User::factory()->create();
        $avans = PersonelAvansi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'tarih' => '2026-06-01',
            'tutar' => 1000,
            'odeme_kanali' => 'diger',
            'durum' => 'taslak',
        ]);

        $onayli = app(PersonelAvansOnayServisi::class)->onayla($firma->id, $avans->id, $onaylayan->id);

        $this->assertSame('onaylandi', $onayli->durum);
        $this->assertSame('onaylandi', $onayli->onay_durumu);
        $this->assertSame($onaylayan->id, $onayli->onaylayan_id);

        $reddedilen = app(PersonelAvansOnayServisi::class)->reddet($firma->id, $avans->id, $onaylayan->id, 'Vazgecildi');

        $this->assertSame('reddedildi', $reddedilen->durum);
        $this->assertSame('Vazgecildi', $reddedilen->aciklama);
    }

    public function test_farkli_firma_avansi_onaylanamaz(): void
    {
        $firmaA = $this->firmaOlustur('AOF');
        $firmaB = $this->firmaOlustur('AOG');
        $personelB = $this->personelOlustur($firmaB);
        $avans = PersonelAvansi::withoutGlobalScopes()->create([
            'firma_id' => $firmaB->id,
            'personel_id' => $personelB->id,
            'tarih' => '2026-06-01',
            'tutar' => 1000,
            'odeme_kanali' => 'diger',
            'durum' => 'taslak',
        ]);

        $this->expectException(ValidationException::class);

        app(PersonelAvansOnayServisi::class)->onayla($firmaA->id, $avans->id, null);
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
            'ad_soyad' => 'Onay Personeli',
            'calisma_tipi' => 'tam_zamanli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 10000,
            'durum' => Personel::DURUM_AKTIF,
        ]);
    }

    private function izinOlustur(Firma $firma, Personel $personel): PersonelIzni
    {
        return PersonelIzni::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'izin_turu' => 'yillik',
            'baslangic_at' => '2026-06-01 09:00:00',
            'bitis_at' => '2026-06-01 18:00:00',
            'durum' => 'onay_bekliyor',
            'onay_durumu' => 'onay_bekliyor',
        ]);
    }
}
