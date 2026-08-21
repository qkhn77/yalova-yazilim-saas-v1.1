<?php

namespace Tests\Feature\PersonelTakip;

use App\Models\Firma;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelGirisCikisi;
use App\Models\User;
use App\Services\PersonelTakip\PersonelPuantajOnayServisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PersonelTakipPuantajOnayServisiTest extends TestCase
{
    use RefreshDatabase;

    public function test_cikisi_olan_puantaj_kaydi_onaylanir(): void
    {
        $firma = $this->firmaOlustur('POA');
        $kayit = $this->kayitOlustur($firma, [
            'giris_at' => '2026-05-31 09:00:00',
            'cikis_at' => '2026-05-31 18:00:00',
        ]);
        $onaylayan = User::factory()->create();

        $sonuc = app(PersonelPuantajOnayServisi::class)->onayla($firma->id, $kayit->id, $onaylayan->id);

        $this->assertSame('onaylandi', $sonuc->onay_durumu);
        $this->assertSame($onaylayan->id, $sonuc->onaylayan_id);
    }

    public function test_cikisi_olmayan_puantaj_kaydi_onaylanamaz(): void
    {
        $firma = $this->firmaOlustur('POB');
        $kayit = $this->kayitOlustur($firma, [
            'giris_at' => '2026-05-31 09:00:00',
            'cikis_at' => null,
        ]);

        $this->expectException(ValidationException::class);

        app(PersonelPuantajOnayServisi::class)->onayla($firma->id, $kayit->id, 99);
    }

    public function test_puantaj_kaydi_reddedilir_ve_aciklama_yazilir(): void
    {
        $firma = $this->firmaOlustur('POR');
        $kayit = $this->kayitOlustur($firma, [
            'giris_at' => '2026-05-31 09:00:00',
            'cikis_at' => null,
        ]);
        $onaylayan = User::factory()->create();

        $sonuc = app(PersonelPuantajOnayServisi::class)->reddet($firma->id, $kayit->id, $onaylayan->id, 'Eksik cikis');

        $this->assertSame('reddedildi', $sonuc->onay_durumu);
        $this->assertSame($onaylayan->id, $sonuc->onaylayan_id);
        $this->assertSame('Eksik cikis', $sonuc->aciklama);
    }

    public function test_farkli_firmanin_puantaj_kaydi_onaylanamaz(): void
    {
        $firmaA = $this->firmaOlustur('POC');
        $firmaB = $this->firmaOlustur('POD');
        $kayit = $this->kayitOlustur($firmaB, [
            'giris_at' => '2026-05-31 09:00:00',
            'cikis_at' => '2026-05-31 18:00:00',
        ]);

        $this->expectException(ValidationException::class);

        app(PersonelPuantajOnayServisi::class)->onayla($firmaA->id, $kayit->id, 99);
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

    /**
     * @param  array<string, mixed>  $ek
     */
    private function kayitOlustur(Firma $firma, array $ek): PersonelGirisCikisi
    {
        $personel = Personel::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad_soyad' => 'Puantaj Personeli',
            'calisma_tipi' => 'tam_zamanli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 10000,
            'durum' => Personel::DURUM_AKTIF,
        ]);

        return PersonelGirisCikisi::withoutGlobalScopes()->create(array_merge([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'tarih' => '2026-05-31',
            'giris_tipi' => 'panel',
            'cikis_tipi' => 'panel',
            'kaynak' => 'panel',
            'onay_durumu' => 'onay_bekliyor',
        ], $ek));
    }
}
