<?php

namespace Tests\Feature\Restoran;

use App\Models\Firma;
use App\Models\Personel\Personel;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Sube;
use App\Services\Restoran\RestoranPaketServisServisi;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RestoranPaketServisServisiTest extends TestCase
{
    use RefreshDatabase;

    public function test_paket_siparis_kurye_atama_yola_cikarma_ve_teslim_akisi(): void
    {
        $firma = $this->firmaOlustur('RPK');
        $sube = $this->subeOlustur($firma, 'MRK');
        $kurye = $this->personelOlustur($firma, $sube, 'Kurye');
        $adisyon = $this->paketAdisyonOlustur($firma, $sube);

        $this->assertSame(RestoranAdisyonu::PAKET_DURUM_HAZIRLANIYOR, $adisyon->paket_durum);

        $servis = app(RestoranPaketServisServisi::class);

        $adisyon = $servis->kuryeAta($adisyon, $kurye->id);
        $this->assertSame((int) $kurye->id, (int) $adisyon->kurye_personel_id);
        $this->assertSame(RestoranAdisyonu::PAKET_DURUM_KURYEE_ATANDI, $adisyon->paket_durum);

        $adisyon = $servis->yolaCikar($adisyon);
        $this->assertSame(RestoranAdisyonu::PAKET_DURUM_YOLDA, $adisyon->paket_durum);

        $adisyon = $servis->teslimEdildi($adisyon);
        $this->assertSame(RestoranAdisyonu::PAKET_DURUM_TESLIM_EDILDI, $adisyon->paket_durum);
        $this->assertNotNull($adisyon->teslimat_at);
    }

    public function test_kurye_atanmadan_paket_yola_cikarilamaz(): void
    {
        $firma = $this->firmaOlustur('RPY');
        $sube = $this->subeOlustur($firma, 'MRK');
        $adisyon = $this->paketAdisyonOlustur($firma, $sube);

        $this->expectException(ValidationException::class);

        app(RestoranPaketServisServisi::class)->yolaCikar($adisyon);
    }

    public function test_farkli_firma_personeli_paket_kuryesi_olarak_atanamaz(): void
    {
        $firma = $this->firmaOlustur('RPF');
        $sube = $this->subeOlustur($firma, 'MRK');
        $digerFirma = $this->firmaOlustur('RPD');
        $digerSube = $this->subeOlustur($digerFirma, 'MRK');
        $digerKurye = $this->personelOlustur($digerFirma, $digerSube, 'Başka Kurye');
        $adisyon = $this->paketAdisyonOlustur($firma, $sube);

        try {
            app(RestoranPaketServisServisi::class)->kuryeAta($adisyon, $digerKurye->id);
            $this->fail('Firma dışı kurye validasyonu bekleniyordu.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('kurye_personel_id', $exception->errors());
        }

        $this->assertNull($adisyon->refresh()->kurye_personel_id);
        $this->assertSame(RestoranAdisyonu::PAKET_DURUM_HAZIRLANIYOR, $adisyon->paket_durum);
    }

    public function test_aktif_firma_disindaki_paket_adisyonunda_islem_yapilamaz(): void
    {
        $firmaA = $this->firmaOlustur('RPA');
        $firmaB = $this->firmaOlustur('RPB');
        $subeB = $this->subeOlustur($firmaB, 'MRK');
        $kuryeB = $this->personelOlustur($firmaB, $subeB, 'Firma B Kurye');
        $adisyonB = $this->paketAdisyonOlustur($firmaB, $subeB);

        app(TenantContextService::class)->firmaAyarla($firmaA);

        try {
            app(RestoranPaketServisServisi::class)->kuryeAta($adisyonB, $kuryeB->id);
            $this->fail('Aktif firma dışı paket servis validasyonu bekleniyordu.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('firma_id', $exception->errors());
        }

        $this->assertNull($adisyonB->refresh()->kurye_personel_id);
        $this->assertSame(RestoranAdisyonu::PAKET_DURUM_HAZIRLANIYOR, $adisyonB->paket_durum);
    }

    public function test_pasif_personel_paket_kuryesi_olarak_atanamaz(): void
    {
        $firma = $this->firmaOlustur('RPP');
        $sube = $this->subeOlustur($firma, 'MRK');
        $pasifKurye = $this->personelOlustur($firma, $sube, 'Pasif Kurye');
        $pasifKurye->forceFill(['durum' => Personel::DURUM_PASIF])->saveQuietly();
        $adisyon = $this->paketAdisyonOlustur($firma, $sube);

        try {
            app(RestoranPaketServisServisi::class)->kuryeAta($adisyon, $pasifKurye->id);
            $this->fail('Pasif kurye validasyonu bekleniyordu.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('kurye_personel_id', $exception->errors());
        }

        $this->assertNull($adisyon->refresh()->kurye_personel_id);
        $this->assertSame(RestoranAdisyonu::PAKET_DURUM_HAZIRLANIYOR, $adisyon->paket_durum);
    }

    public function test_paket_siparis_tahmini_teslimat_planlanir(): void
    {
        $firma = $this->firmaOlustur('RPT');
        $sube = $this->subeOlustur($firma, 'MRK');
        $adisyon = $this->paketAdisyonOlustur($firma, $sube);
        $tahminiTeslimat = now()->addMinutes(45);

        $adisyon = app(RestoranPaketServisServisi::class)->teslimatPlanla($adisyon, $tahminiTeslimat, 'Apartman girisi');

        $this->assertSame($tahminiTeslimat->format('Y-m-d H:i'), $adisyon->tahmini_teslimat_at?->format('Y-m-d H:i'));
        $this->assertSame('Apartman girisi', $adisyon->teslimat_notu);
    }

    public function test_paket_siparis_teslimat_notu_limitini_uygular(): void
    {
        $firma = $this->firmaOlustur('RPNOT');
        $sube = $this->subeOlustur($firma, 'MRK');
        $adisyon = $this->paketAdisyonOlustur($firma, $sube);

        try {
            app(RestoranPaketServisServisi::class)->teslimatPlanla($adisyon, now()->addMinutes(30), str_repeat('x', 301));
            $this->fail('Teslimat notu validasyonu bekleniyordu.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('teslimat_notu', $exception->errors());
        }

        $this->assertNull($adisyon->refresh()->tahmini_teslimat_at);
        $this->assertNull($adisyon->teslimat_notu);
    }

    public function test_masa_siparisinde_paket_servis_islemi_yapilamaz(): void
    {
        $firma = $this->firmaOlustur('RPM');
        $sube = $this->subeOlustur($firma, 'MRK');
        $kurye = $this->personelOlustur($firma, $sube, 'Kurye');
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'siparis_tipi' => 'masa',
        ]);

        $this->expectException(ValidationException::class);

        app(RestoranPaketServisServisi::class)->kuryeAta($adisyon, $kurye->id);
    }

    public function test_paket_siparis_iptali_adisyonu_da_iptal_eder(): void
    {
        $firma = $this->firmaOlustur('RPI');
        $sube = $this->subeOlustur($firma, 'MRK');
        $adisyon = $this->paketAdisyonOlustur($firma, $sube);

        $adisyon = app(RestoranPaketServisServisi::class)->iptalEt($adisyon, 'Müşteri vazgeçti');

        $this->assertSame(RestoranAdisyonu::PAKET_DURUM_IPTAL, $adisyon->paket_durum);
        $this->assertSame(RestoranAdisyonu::DURUM_IPTAL, $adisyon->durum);
        $this->assertNotNull($adisyon->kapanis_at);
        $this->assertStringContainsString('Müşteri vazgeçti', (string) $adisyon->notlar);
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

    private function personelOlustur(Firma $firma, Sube $sube, string $ad): Personel
    {
        return Personel::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'ad_soyad' => $ad,
            'calisma_tipi' => 'tam_zamanli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 0,
            'durum' => Personel::DURUM_AKTIF,
        ]);
    }

    private function paketAdisyonOlustur(Firma $firma, Sube $sube): RestoranAdisyonu
    {
        return RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'siparis_tipi' => 'paket',
            'teslimat_telefon' => '5551112233',
            'teslimat_adresi' => 'Test teslimat adresi',
        ]);
    }
}
