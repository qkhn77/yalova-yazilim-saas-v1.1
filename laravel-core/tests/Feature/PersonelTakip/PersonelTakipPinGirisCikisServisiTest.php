<?php

namespace Tests\Feature\PersonelTakip;

use App\Models\Firma;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelGirisCikisi;
use App\Models\Personel\PersonelVardiyasi;
use App\Models\Sube;
use App\Services\PersonelTakip\PersonelPinGirisCikisServisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PersonelTakipPinGirisCikisServisiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pin_ilk_okutmada_giris_ikinci_okutmada_cikis_yapar(): void
    {
        $firma = $this->firmaOlustur('PIN');
        $personel = $this->personelOlustur($firma, '1234');
        $vardiya = PersonelVardiyasi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'tarih' => '2026-05-31',
            'baslangic_at' => '2026-05-31 10:00:00',
            'bitis_at' => '2026-05-31 18:00:00',
            'durum' => 'planlandi',
        ]);

        $servis = app(PersonelPinGirisCikisServisi::class);
        $giris = $servis->pinIleIslemYap($firma->id, '1234', null, Carbon::parse('2026-05-31 10:10:00'));
        $cikis = $servis->pinIleIslemYap($firma->id, '1234', null, Carbon::parse('2026-05-31 18:30:00'));

        $this->assertSame($giris->id, $cikis->id);
        $this->assertSame($vardiya->id, $giris->vardiya_id);
        $this->assertSame('pin', $cikis->fresh()->cikis_tipi);
        $this->assertSame(10, $cikis->fresh()->gec_kalma_dakika);
        $this->assertSame(30, $cikis->fresh()->fazla_mesai_dakika);
    }

    public function test_pin_kodu_firma_icinde_benzersiz_olmalidir(): void
    {
        $firma = $this->firmaOlustur('PNB');
        $this->personelOlustur($firma, '4444');

        $this->expectException(ValidationException::class);

        $this->personelOlustur($firma, '4444');
    }

    public function test_pin_kodu_duz_metin_saklanmaz_hash_ile_dogrulanir(): void
    {
        $firma = $this->firmaOlustur('PNHSH');

        $personel = $this->personelOlustur($firma, '4321')->fresh();

        $this->assertNull($personel->pin_kodu);
        $this->assertNotEmpty($personel->pin_kodu_hash);
        $this->assertTrue(Hash::check('4321', (string) $personel->pin_kodu_hash));
        $this->assertNotSame('4321', $personel->pin_kodu_hash);
    }

    public function test_hatali_pin_aktif_personel_bulamaz(): void
    {
        $firma = $this->firmaOlustur('PNH');

        $this->expectException(ValidationException::class);

        app(PersonelPinGirisCikisServisi::class)->pinIleIslemYap($firma->id, '9999', null, Carbon::parse('2026-05-31 10:00:00'));
    }

    public function test_pin_terminali_farkli_firmanin_subesini_kullanamaz(): void
    {
        $firmaA = $this->firmaOlustur('PNA');
        $firmaB = $this->firmaOlustur('PNC');
        $this->personelOlustur($firmaA, '1234');
        $subeB = Sube::withoutGlobalScopes()->create([
            'firma_id' => $firmaB->id,
            'ad' => 'Başka Firma Şube',
            'kod' => 'BFS',
            'aktif_mi' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(PersonelPinGirisCikisServisi::class)->pinIleIslemYap($firmaA->id, '1234', $subeB->id, Carbon::parse('2026-05-31 10:00:00'));
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

    private function personelOlustur(Firma $firma, string $pin): Personel
    {
        return Personel::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad_soyad' => 'PIN Personeli',
            'calisma_tipi' => 'tam_zamanli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 10000,
            'pin_kodu' => $pin,
            'durum' => Personel::DURUM_AKTIF,
        ]);
    }
}
