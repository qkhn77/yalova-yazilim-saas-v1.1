<?php

namespace Tests\Feature\PersonelTakip;

use App\Models\Firma;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelAyari;
use App\Services\PersonelTakip\PersonelAyarlariServisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PersonelTakipAyarlariServisiTest extends TestCase
{
    use RefreshDatabase;

    public function test_personel_genel_ayarlari_varsayilanlari_dondurur(): void
    {
        $firma = $this->firmaOlustur('PAY');

        $ayarlar = app(PersonelAyarlariServisi::class)->genel($firma->id);

        $this->assertSame('TRY', $ayarlar['para_birimi']);
        $this->assertSame(45, $ayarlar['haftalik_calisma_saati']);
        $this->assertFalse($ayarlar['pin_zorunlu']);
    }

    public function test_personel_genel_ayarlari_normalize_edilerek_kaydedilir(): void
    {
        $firma = $this->firmaOlustur('PAZ');

        $kayit = app(PersonelAyarlariServisi::class)->kaydetGenel($firma->id, [
            'para_birimi' => 'usd',
            'gunluk_calisma_saati' => 8,
            'haftalik_calisma_saati' => 48,
            'fazla_mesai_katsayi' => 0.5,
            'pin_zorunlu' => true,
            'otomatik_maas_hesaplama' => true,
        ]);

        $this->assertSame('USD', $kayit['para_birimi']);
        $this->assertSame(1.0, $kayit['fazla_mesai_katsayi']);
        $this->assertTrue($kayit['pin_zorunlu']);
        $this->assertSame(1, PersonelAyari::withoutGlobalScopes()->where('firma_id', $firma->id)->count());
    }

    public function test_pin_zorunlu_ayarinda_pin_olmayan_personel_kaydedilemez(): void
    {
        $firma = $this->firmaOlustur('PAP');

        app(PersonelAyarlariServisi::class)->kaydetGenel($firma->id, [
            'pin_zorunlu' => true,
        ]);

        try {
            Personel::withoutGlobalScopes()->create([
                'firma_id' => $firma->id,
                'ad_soyad' => 'PIN Personeli',
                'calisma_tipi' => 'tam_zamanli',
                'maas_tipi' => 'aylik',
                'durum' => Personel::DURUM_AKTIF,
            ]);

            $this->fail('PIN zorunlu ayarinda PIN olmadan personel kaydedildi.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('pin_kodu', $exception->errors());
        }

        $personel = Personel::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad_soyad' => 'PINli Personel',
            'calisma_tipi' => 'tam_zamanli',
            'maas_tipi' => 'aylik',
            'pin_kodu' => '1234',
            'durum' => Personel::DURUM_AKTIF,
        ]);

        $this->assertNull($personel->pin_kodu);
        $this->assertTrue(Hash::check('1234', (string) $personel->pin_kodu_hash));
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
}
