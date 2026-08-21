<?php

namespace Tests\Feature\Restoran;

use App\Models\Firma;
use App\Models\Restoran\RestoranAdisyonKalemi;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Restoran\RestoranMasasi;
use App\Models\Sube;
use App\Services\Restoran\RestoranMasaOperasyonServisi;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RestoranMasaOperasyonServisiTest extends TestCase
{
    use RefreshDatabase;

    public function test_bos_masada_adisyon_acilir_ve_masa_dolu_olur(): void
    {
        $firma = $this->firmaOlustur('RMA');
        $sube = $this->subeOlustur($firma, 'MRK');
        $masa = $this->masaOlustur($firma, $sube, 'A1');

        $adisyon = app(RestoranMasaOperasyonServisi::class)->masaAdisyonuAc($masa);

        $this->assertSame((int) $masa->id, (int) $adisyon->masa_id);
        $this->assertSame(RestoranAdisyonu::DURUM_ACIK, $adisyon->durum);
        $this->assertSame(RestoranMasasi::DURUM_DOLU, $masa->refresh()->durum);
    }

    public function test_acik_adisyon_odemeye_alinir(): void
    {
        $firma = $this->firmaOlustur('RMOA');
        $sube = $this->subeOlustur($firma, 'MRK');
        $masa = $this->masaOlustur($firma, $sube, 'O1');
        $adisyon = $this->adisyonOlustur($firma, $sube, $masa);

        $sonuc = app(RestoranMasaOperasyonServisi::class)->odemeyeAl($adisyon);

        $this->assertSame(RestoranAdisyonu::DURUM_ODEMEDE, $sonuc->durum);
        $this->assertSame(RestoranMasasi::DURUM_DOLU, $masa->refresh()->durum);
    }

    public function test_aktif_firma_disindaki_masada_adisyon_acilamaz(): void
    {
        $firmaA = $this->firmaOlustur('RMFA');
        $firmaB = $this->firmaOlustur('RMFB');
        $subeB = $this->subeOlustur($firmaB, 'MRK');
        $masaB = $this->masaOlustur($firmaB, $subeB, 'B1');

        app(TenantContextService::class)->firmaAyarla($firmaA);

        try {
            app(RestoranMasaOperasyonServisi::class)->masaAdisyonuAc($masaB);
            $this->fail('Aktif firma dışı masa operasyonu validasyonu bekleniyordu.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('firma_id', $exception->errors());
        }

        $this->assertSame(RestoranMasasi::DURUM_BOS, $masaB->refresh()->durum);
    }

    public function test_aktif_firma_disindaki_adisyon_odemeye_alinamaz(): void
    {
        $firmaA = $this->firmaOlustur('RMQA');
        $firmaB = $this->firmaOlustur('RMQB');
        $subeB = $this->subeOlustur($firmaB, 'MRK');
        $masaB = $this->masaOlustur($firmaB, $subeB, 'B2');
        $adisyonB = $this->adisyonOlustur($firmaB, $subeB, $masaB);

        app(TenantContextService::class)->firmaAyarla($firmaA);

        try {
            app(RestoranMasaOperasyonServisi::class)->odemeyeAl($adisyonB);
            $this->fail('Aktif firma dışı adisyon operasyonu validasyonu bekleniyordu.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('firma_id', $exception->errors());
        }

        $this->assertSame(RestoranAdisyonu::DURUM_ACIK, $adisyonB->refresh()->durum);
    }

    public function test_acik_adisyon_bos_masaya_tasinir_ve_masa_durumlari_guncellenir(): void
    {
        $firma = $this->firmaOlustur('RMO');
        $sube = $this->subeOlustur($firma, 'MRK');
        $eskiMasa = $this->masaOlustur($firma, $sube, 'M1');
        $yeniMasa = $this->masaOlustur($firma, $sube, 'M2');
        $adisyon = $this->adisyonOlustur($firma, $sube, $eskiMasa);

        $sonuc = app(RestoranMasaOperasyonServisi::class)->masaTasi($adisyon, $yeniMasa);

        $this->assertSame((int) $yeniMasa->id, (int) $sonuc->masa_id);
        $this->assertSame(RestoranMasasi::DURUM_BOS, $eskiMasa->refresh()->durum);
        $this->assertSame(RestoranMasasi::DURUM_DOLU, $yeniMasa->refresh()->durum);
    }

    public function test_adisyon_dolu_veya_firma_disi_masaya_tasinamaz(): void
    {
        $firma = $this->firmaOlustur('RMD');
        $digerFirma = $this->firmaOlustur('RMY');
        $sube = $this->subeOlustur($firma, 'MRK');
        $digerSube = $this->subeOlustur($digerFirma, 'DG');
        $masa = $this->masaOlustur($firma, $sube, 'M1');
        $doluMasa = $this->masaOlustur($firma, $sube, 'M2');
        $digerMasa = $this->masaOlustur($digerFirma, $digerSube, 'D1');
        $adisyon = $this->adisyonOlustur($firma, $sube, $masa);
        $this->adisyonOlustur($firma, $sube, $doluMasa);

        $servis = app(RestoranMasaOperasyonServisi::class);

        try {
            $servis->masaTasi($adisyon, $doluMasa);
            $this->fail('Dolu masaya taşıma engellenmeliydi.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('masa_id', $exception->errors());
        }

        $this->expectException(ValidationException::class);

        $servis->masaTasi($adisyon, $digerMasa);
    }

    public function test_masalari_birlestir_kalemleri_hedefe_tasir_ve_kaynak_masayi_bosaltir(): void
    {
        $firma = $this->firmaOlustur('RMB');
        $sube = $this->subeOlustur($firma, 'MRK');
        $kaynakMasa = $this->masaOlustur($firma, $sube, 'K1');
        $hedefMasa = $this->masaOlustur($firma, $sube, 'H1');
        $kaynakAdisyon = $this->adisyonOlustur($firma, $sube, $kaynakMasa);
        $hedefAdisyon = $this->adisyonOlustur($firma, $sube, $hedefMasa);

        $this->kalemOlustur($firma, $kaynakAdisyon, 'Çorba', 1, 50, 10);
        $this->kalemOlustur($firma, $hedefAdisyon, 'Kebap', 1, 100, 10);

        $sonuc = app(RestoranMasaOperasyonServisi::class)->masalariBirlestir($kaynakAdisyon, $hedefAdisyon);

        $this->assertSame('165.00', (string) $sonuc->genel_toplam);
        $this->assertSame(RestoranAdisyonu::DURUM_IPTAL, $kaynakAdisyon->refresh()->durum);
        $this->assertSame(RestoranMasasi::DURUM_BOS, $kaynakMasa->refresh()->durum);
        $this->assertSame(RestoranMasasi::DURUM_DOLU, $hedefMasa->refresh()->durum);
        $this->assertSame(2, RestoranAdisyonKalemi::withoutGlobalScopes()->where('adisyon_id', $hedefAdisyon->id)->count());
    }

    public function test_adisyonu_bol_secilen_kalemleri_yeni_adisyona_tasir(): void
    {
        $firma = $this->firmaOlustur('RMS');
        $sube = $this->subeOlustur($firma, 'MRK');
        $kaynakMasa = $this->masaOlustur($firma, $sube, 'K1');
        $hedefMasa = $this->masaOlustur($firma, $sube, 'H1');
        $kaynakAdisyon = $this->adisyonOlustur($firma, $sube, $kaynakMasa);
        $kalemA = $this->kalemOlustur($firma, $kaynakAdisyon, 'Ana Yemek', 1, 200, 10);
        $kalemB = $this->kalemOlustur($firma, $kaynakAdisyon, 'İçecek', 2, 25, 20);

        $yeniAdisyon = app(RestoranMasaOperasyonServisi::class)->adisyonuBol($kaynakAdisyon, [$kalemB->id], $hedefMasa);

        $this->assertSame((int) $hedefMasa->id, (int) $yeniAdisyon->masa_id);
        $this->assertSame('60.00', (string) $yeniAdisyon->genel_toplam);
        $this->assertSame('220.00', (string) $kaynakAdisyon->refresh()->genel_toplam);
        $this->assertSame((int) $kaynakAdisyon->id, (int) $kalemA->refresh()->adisyon_id);
        $this->assertSame((int) $yeniAdisyon->id, (int) $kalemB->refresh()->adisyon_id);
        $this->assertSame(RestoranMasasi::DURUM_DOLU, $kaynakMasa->refresh()->durum);
        $this->assertSame(RestoranMasasi::DURUM_DOLU, $hedefMasa->refresh()->durum);
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

    private function masaOlustur(Firma $firma, Sube $sube, string $kod): RestoranMasasi
    {
        return RestoranMasasi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'ad' => 'Masa '.$kod,
            'kod' => $kod,
        ]);
    }

    private function adisyonOlustur(Firma $firma, Sube $sube, RestoranMasasi $masa): RestoranAdisyonu
    {
        return RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'masa_id' => $masa->id,
        ]);
    }

    private function kalemOlustur(
        Firma $firma,
        RestoranAdisyonu $adisyon,
        string $urunAdi,
        float $miktar,
        float $birimFiyat,
        float $kdvOrani
    ): RestoranAdisyonKalemi {
        return RestoranAdisyonKalemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_id' => $adisyon->id,
            'urun_adi' => $urunAdi,
            'miktar' => $miktar,
            'birim_fiyat' => $birimFiyat,
            'kdv_orani' => $kdvOrani,
        ]);
    }
}
