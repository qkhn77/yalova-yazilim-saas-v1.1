<?php

namespace Tests\Feature\PersonelTakip;

use App\Models\Firma;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelGirisCikisi;
use App\Models\Personel\PersonelIzni;
use App\Models\Personel\PersonelVardiyasi;
use App\Models\Sube;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PersonelTakipGirisIzinKuralTest extends TestCase
{
    use RefreshDatabase;

    public function test_giris_cikis_vardiyadan_sube_tarih_ve_dakikalari_hesaplar(): void
    {
        $firma = $this->firmaOlustur('GCK');
        $sube = $this->subeOlustur($firma);
        $personel = $this->personelOlustur($firma, 'Puantaj Personeli', $sube);
        $vardiya = $this->vardiyaOlustur($firma, $personel, $sube);

        $kayit = PersonelGirisCikisi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'vardiya_id' => $vardiya->id,
            'giris_at' => '2026-05-31 10:15:00',
            'cikis_at' => '2026-05-31 17:45:00',
            'kaynak' => 'panel',
            'onay_durumu' => 'onay_bekliyor',
        ]);

        $this->assertSame($sube->id, $kayit->sube_id);
        $this->assertSame('2026-05-31', $kayit->tarih?->toDateString());
        $this->assertSame(15, $kayit->gec_kalma_dakika);
        $this->assertSame(15, $kayit->erken_cikis_dakika);
        $this->assertSame(0, $kayit->fazla_mesai_dakika);
        $this->assertSame(30, $kayit->eksik_calisma_dakika);
    }

    public function test_giris_cikis_farkli_personelin_vardiyasina_baglanamaz(): void
    {
        $firma = $this->firmaOlustur('GCU');
        $sube = $this->subeOlustur($firma);
        $personelA = $this->personelOlustur($firma, 'Personel A', $sube);
        $personelB = $this->personelOlustur($firma, 'Personel B', $sube);
        $vardiya = $this->vardiyaOlustur($firma, $personelA, $sube);

        $this->expectException(ValidationException::class);

        PersonelGirisCikisi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personelB->id,
            'vardiya_id' => $vardiya->id,
            'giris_at' => '2026-05-31 10:00:00',
            'cikis_at' => '2026-05-31 18:00:00',
        ]);
    }

    public function test_giris_cikis_cikis_zamani_giristen_once_olamaz(): void
    {
        $firma = $this->firmaOlustur('GCZ');
        $personel = $this->personelOlustur($firma, 'Zaman Personeli');

        $this->expectException(ValidationException::class);

        PersonelGirisCikisi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'giris_at' => '2026-05-31 18:00:00',
            'cikis_at' => '2026-05-31 10:00:00',
        ]);
    }

    public function test_giris_cikis_farkli_firmanin_subesine_yazilamaz(): void
    {
        $firmaA = $this->firmaOlustur('GCA');
        $firmaB = $this->firmaOlustur('GCB');
        $personel = $this->personelOlustur($firmaA, 'Yanlis Sube Giris');
        $subeB = $this->subeOlustur($firmaB);

        $this->expectException(ValidationException::class);

        PersonelGirisCikisi::withoutGlobalScopes()->create([
            'firma_id' => $firmaA->id,
            'sube_id' => $subeB->id,
            'personel_id' => $personel->id,
            'giris_at' => '2026-05-31 10:00:00',
            'cikis_at' => '2026-05-31 18:00:00',
        ]);
    }

    public function test_onayli_izin_aktif_vardiya_ustune_yazilamaz(): void
    {
        $firma = $this->firmaOlustur('IZV');
        $personel = $this->personelOlustur($firma, 'İzin Personeli');
        $this->vardiyaOlustur($firma, $personel);

        $this->expectException(ValidationException::class);

        PersonelIzni::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'izin_turu' => 'yillik',
            'baslangic_at' => '2026-05-31 12:00:00',
            'bitis_at' => '2026-05-31 16:00:00',
            'durum' => 'onaylandi',
            'onay_durumu' => 'onaylandi',
        ]);
    }

    public function test_izin_tarih_gun_ve_saat_degerlerini_hesaplar(): void
    {
        $firma = $this->firmaOlustur('IZH');
        $personel = $this->personelOlustur($firma, 'Hesaplı İzin Personeli');

        $izin = PersonelIzni::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'izin_turu' => 'mazeret',
            'baslangic_at' => '2026-05-31 09:00:00',
            'bitis_at' => '2026-05-31 17:00:00',
            'durum' => 'onay_bekliyor',
        ]);

        $this->assertSame('2026-05-31', $izin->baslangic_tarihi?->toDateString());
        $this->assertSame('2026-05-31', $izin->bitis_tarihi?->toDateString());
        $this->assertSame('8.00', $izin->saat_sayisi);
        $this->assertSame('1.00', $izin->gun_sayisi);
        $this->assertSame('onay_bekliyor', $izin->onay_durumu);
    }

    public function test_onayli_izin_araliklari_cakisamaz(): void
    {
        $firma = $this->firmaOlustur('IZC');
        $personel = $this->personelOlustur($firma, 'Çakışan İzin Personeli');

        PersonelIzni::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'izin_turu' => 'yillik',
            'baslangic_at' => '2026-05-31 09:00:00',
            'bitis_at' => '2026-05-31 18:00:00',
            'durum' => 'onaylandi',
            'onay_durumu' => 'onaylandi',
        ]);

        $this->expectException(ValidationException::class);

        PersonelIzni::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'izin_turu' => 'mazeret',
            'baslangic_at' => '2026-05-31 12:00:00',
            'bitis_at' => '2026-05-31 15:00:00',
            'durum' => 'onaylandi',
            'onay_durumu' => 'onaylandi',
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

    private function subeOlustur(Firma $firma): Sube
    {
        return Sube::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad' => 'Merkez',
            'kod' => 'MRK',
            'aktif_mi' => true,
        ]);
    }

    private function personelOlustur(Firma $firma, string $ad, ?Sube $sube = null): Personel
    {
        return Personel::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube?->id,
            'ad_soyad' => $ad,
            'calisma_tipi' => 'tam_zamanli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 10000,
            'durum' => Personel::DURUM_AKTIF,
        ]);
    }

    private function vardiyaOlustur(Firma $firma, Personel $personel, ?Sube $sube = null): PersonelVardiyasi
    {
        return PersonelVardiyasi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube?->id,
            'personel_id' => $personel->id,
            'tarih' => '2026-05-31',
            'baslangic_at' => '2026-05-31 10:00:00',
            'bitis_at' => '2026-05-31 18:00:00',
            'mola_dakika' => 30,
            'durum' => 'planlandi',
        ]);
    }
}
