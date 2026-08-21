<?php

namespace Tests\Feature\PersonelTakip;

use App\Models\Firma;
use App\Models\FirmaKullanici;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelDepartmani;
use App\Models\Personel\PersonelGorevi;
use App\Models\Personel\PersonelIzni;
use App\Models\Personel\PersonelVardiyaSablonu;
use App\Models\Personel\PersonelVardiyasi;
use App\Models\Sube;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PersonelTakipVardiyaKuralTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_personel_farkli_firmanin_departmanina_baglanamaz(): void
    {
        $firmaA = $this->firmaOlustur('PKA');
        $firmaB = $this->firmaOlustur('PKB');

        $departmanB = PersonelDepartmani::withoutGlobalScopes()->create([
            'firma_id' => $firmaB->id,
            'ad' => 'Başka Firma Operasyon',
            'kod' => 'BFO',
            'aktif_mi' => true,
        ]);

        $this->expectException(ValidationException::class);

        Personel::withoutGlobalScopes()->create([
            'firma_id' => $firmaA->id,
            'departman_id' => $departmanB->id,
            'ad_soyad' => 'Tenant Hatalı Personel',
            'calisma_tipi' => 'tam_zamanli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 10000,
            'durum' => Personel::DURUM_AKTIF,
        ]);
    }

    public function test_personel_departmani_gorevi_ve_subesi_uyumlu_olmalidir(): void
    {
        $firma = $this->firmaOlustur('PKU');
        $subeA = Sube::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad' => 'Merkez',
            'kod' => 'MRK',
            'aktif_mi' => true,
        ]);
        $subeB = Sube::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad' => 'Şube 2',
            'kod' => 'SB2',
            'aktif_mi' => true,
        ]);
        $departman = PersonelDepartmani::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $subeB->id,
            'ad' => 'Servis',
            'kod' => 'SRV',
            'aktif_mi' => true,
        ]);
        $gorev = PersonelGorevi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'departman_id' => $departman->id,
            'ad' => 'Garson',
            'kod' => 'GAR',
            'aktif_mi' => true,
        ]);

        $this->expectException(ValidationException::class);

        Personel::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $subeA->id,
            'departman_id' => $departman->id,
            'gorev_id' => $gorev->id,
            'ad_soyad' => 'Şube Uyumsuz Personel',
            'calisma_tipi' => 'tam_zamanli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 10000,
            'durum' => Personel::DURUM_AKTIF,
        ]);
    }

    public function test_personel_no_bos_birakilirsa_firma_bazli_uretilir(): void
    {
        $firma = $this->firmaOlustur('PNO');

        $ilk = $this->personelOlustur($firma, 'Numara Bir');
        $ikinci = $this->personelOlustur($firma, 'Numara Iki');

        $this->assertSame('P-000001', $ilk->personel_no);
        $this->assertSame('P-000002', $ikinci->personel_no);
    }

    public function test_personel_no_ayni_firmada_tekrarlanamaz(): void
    {
        $firma = $this->firmaOlustur('PNT');
        $this->personelOlustur($firma, 'Numarali Personel', Personel::DURUM_AKTIF, [
            'personel_no' => 'PX-1',
        ]);

        $this->expectException(ValidationException::class);

        $this->personelOlustur($firma, 'Tekrar Personel', Personel::DURUM_AKTIF, [
            'personel_no' => 'PX-1',
        ]);
    }

    public function test_personel_kullanicisi_ayni_firmaya_ait_olmalidir(): void
    {
        $firmaA = $this->firmaOlustur('PKA1');
        $firmaB = $this->firmaOlustur('PKB1');
        $kullanici = User::factory()->create();
        FirmaKullanici::query()->create([
            'firma_id' => $firmaB->id,
            'kullanici_id' => $kullanici->id,
            'durum' => 'aktif',
        ]);

        $this->expectException(ValidationException::class);

        $this->personelOlustur($firmaA, 'Yanlis Kullanici Personeli', Personel::DURUM_AKTIF, [
            'kullanici_id' => $kullanici->id,
        ]);
    }

    public function test_cakisan_vardiya_engellenir(): void
    {
        $firma = $this->firmaOlustur('PVK');
        $personel = $this->personelOlustur($firma, 'Çakışma Personeli');

        PersonelVardiyasi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'tarih' => '2026-05-31',
            'baslangic_at' => '2026-05-31 10:00:00',
            'bitis_at' => '2026-05-31 18:00:00',
            'mola_dakika' => 30,
            'durum' => 'planlandi',
        ]);

        $this->expectException(ValidationException::class);

        PersonelVardiyasi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'tarih' => '2026-05-31',
            'baslangic_at' => '2026-05-31 17:00:00',
            'bitis_at' => '2026-05-31 23:00:00',
            'mola_dakika' => 30,
            'durum' => 'planlandi',
        ]);
    }

    public function test_onayli_izindeki_personele_vardiya_yazilamaz(): void
    {
        $firma = $this->firmaOlustur('PVI');
        $personel = $this->personelOlustur($firma, 'İzinli Personel');

        PersonelIzni::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'izin_turu' => 'yillik_izin',
            'baslangic_at' => '2026-05-31 00:00:00',
            'bitis_at' => '2026-06-01 23:59:00',
            'gun_sayisi' => 2,
            'durum' => 'onaylandi',
            'onay_durumu' => 'onaylandi',
        ]);

        $this->expectException(ValidationException::class);

        PersonelVardiyasi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'tarih' => '2026-05-31',
            'baslangic_at' => '2026-05-31 10:00:00',
            'bitis_at' => '2026-05-31 18:00:00',
            'durum' => 'planlandi',
        ]);
    }

    public function test_pasif_personele_vardiya_yazilamaz(): void
    {
        $firma = $this->firmaOlustur('PVP');
        $personel = $this->personelOlustur($firma, 'Pasif Personel', Personel::DURUM_PASIF);

        $this->expectException(ValidationException::class);

        PersonelVardiyasi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'tarih' => '2026-05-31',
            'baslangic_at' => '2026-05-31 10:00:00',
            'bitis_at' => '2026-05-31 18:00:00',
            'durum' => 'planlandi',
        ]);
    }

    public function test_vardiya_farkli_firmanin_subesine_yazilamaz(): void
    {
        $firmaA = $this->firmaOlustur('PVA');
        $firmaB = $this->firmaOlustur('PVB');
        $personel = $this->personelOlustur($firmaA, 'Yanlis Sube Vardiya');
        $subeB = Sube::withoutGlobalScopes()->create([
            'firma_id' => $firmaB->id,
            'ad' => 'Baska Firma Sube',
            'kod' => 'BFS',
            'aktif_mi' => true,
        ]);

        $this->expectException(ValidationException::class);

        PersonelVardiyasi::withoutGlobalScopes()->create([
            'firma_id' => $firmaA->id,
            'sube_id' => $subeB->id,
            'personel_id' => $personel->id,
            'tarih' => '2026-05-31',
            'baslangic_at' => '2026-05-31 10:00:00',
            'bitis_at' => '2026-05-31 18:00:00',
            'durum' => 'planlandi',
        ]);
    }

    public function test_vardiya_sablonu_tarih_ile_baslangic_ve_bitis_saatlerini_doldurur(): void
    {
        $firma = $this->firmaOlustur('PVS');
        $personel = $this->personelOlustur($firma, 'Sablonlu Personel');
        $sablon = PersonelVardiyaSablonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad' => 'Sabah',
            'baslangic_saati' => '08:30',
            'bitis_saati' => '16:30',
            'mola_dakika' => 45,
            'aktif_mi' => true,
        ]);

        $vardiya = PersonelVardiyasi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'vardiya_sablonu_id' => $sablon->id,
            'tarih' => '2026-06-01',
            'durum' => 'planlandi',
        ]);

        $this->assertSame('2026-06-01 08:30:00', $vardiya->baslangic_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-01 16:30:00', $vardiya->bitis_at->format('Y-m-d H:i:s'));
        $this->assertSame(45, $vardiya->mola_dakika);
    }

    public function test_gece_vardiya_sablonu_bitisi_ertesi_gune_tasir(): void
    {
        $firma = $this->firmaOlustur('PVG');
        $personel = $this->personelOlustur($firma, 'Gece Personeli');
        $sablon = PersonelVardiyaSablonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad' => 'Gece',
            'baslangic_saati' => '22:00',
            'bitis_saati' => '06:00',
            'aktif_mi' => true,
        ]);

        $vardiya = PersonelVardiyasi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'vardiya_sablonu_id' => $sablon->id,
            'tarih' => '2026-06-01',
            'durum' => 'planlandi',
        ]);

        $this->assertSame('2026-06-01 22:00:00', $vardiya->baslangic_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-02 06:00:00', $vardiya->bitis_at->format('Y-m-d H:i:s'));
    }

    public function test_farkli_firmanin_vardiya_sablonu_kullanilamaz(): void
    {
        $firmaA = $this->firmaOlustur('PVF');
        $firmaB = $this->firmaOlustur('PVH');
        $personel = $this->personelOlustur($firmaA, 'Yanlis Sablon Personeli');
        $sablon = PersonelVardiyaSablonu::withoutGlobalScopes()->create([
            'firma_id' => $firmaB->id,
            'ad' => 'Baska Firma Sabah',
            'baslangic_saati' => '08:00',
            'bitis_saati' => '16:00',
            'aktif_mi' => true,
        ]);

        $this->expectException(ValidationException::class);

        PersonelVardiyasi::withoutGlobalScopes()->create([
            'firma_id' => $firmaA->id,
            'personel_id' => $personel->id,
            'vardiya_sablonu_id' => $sablon->id,
            'tarih' => '2026-06-01',
            'durum' => 'planlandi',
        ]);
    }

    public function test_pasif_vardiya_sablonu_kullanilamaz(): void
    {
        $firma = $this->firmaOlustur('PVX');
        $personel = $this->personelOlustur($firma, 'Pasif Sablon Personeli');
        $sablon = PersonelVardiyaSablonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad' => 'Pasif Sabah',
            'baslangic_saati' => '08:00',
            'bitis_saati' => '16:00',
            'aktif_mi' => false,
        ]);

        $this->expectException(ValidationException::class);

        PersonelVardiyasi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'vardiya_sablonu_id' => $sablon->id,
            'tarih' => '2026-06-01',
            'durum' => 'planlandi',
        ]);
    }

    private function personelOlustur(Firma $firma, string $ad, string $durum = Personel::DURUM_AKTIF, array $ek = []): Personel
    {
        return Personel::withoutGlobalScopes()->create(array_merge([
            'firma_id' => $firma->id,
            'ad_soyad' => $ad,
            'calisma_tipi' => 'tam_zamanli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 10000,
            'durum' => $durum,
        ], $ek));
    }
}
