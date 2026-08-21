<?php

namespace Tests\Feature\Restoran;

use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\StokHareketi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Restoran\RestoranAdisyonTahsilati;
use App\Models\Restoran\RestoranAdisyonKalemi;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\User;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Services\Restoran\RestoranFaturaServisi;
use App\Services\Restoran\RestoranTahsilatServisi;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RestoranFaturaServisiTest extends TestCase
{
    use RefreshDatabase;

    public function test_kapali_restoran_adisyonundan_bekleyen_fatura_olusturulur_ve_idempotenttir(): void
    {
        $firma = $this->firmaOlustur('RFAT');
        $cari = $this->cariOlustur($firma, 'C-RFAT');
        $stok = StokKarti::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kod' => 'REST-FAT-STOK',
            'ad' => 'Faturali Restoran Urunu',
            'tur' => 'ticari_mal',
            'birim' => 'AD',
            'satis_fiyati' => 200,
            'kdv_orani' => 10,
            'stok_takip' => true,
            'stok_miktari' => 10,
            'guncel_birim_maliyet' => 80,
            'stok_degeri' => 800,
            'durum' => 'aktif',
        ]);

        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
            'adisyon_no' => 'AD-FAT-1',
            'acilis_at' => '2026-06-01 12:00:00',
            'durum' => RestoranAdisyonu::DURUM_ACIK,
            'para_birimi' => 'TRY',
        ]);

        RestoranAdisyonKalemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_id' => $adisyon->id,
            'stok_karti_id' => $stok->id,
            'urun_adi' => 'Ana Yemek',
            'miktar' => 2,
            'birim_fiyat' => 200,
            'kdv_orani' => 10,
            'iskonto_tutari' => 20,
        ]);
        $adisyon->refresh()->forceFill([
            'kapanis_at' => '2026-06-01 13:00:00',
            'durum' => RestoranAdisyonu::DURUM_KAPANDI,
        ])->save();

        $this->muhasebeBaglamiHazirla($firma);

        $fatura = app(RestoranFaturaServisi::class)->bekleyenFaturaOlustur($adisyon->refresh(), 'e_arsiv');
        $tekrar = app(RestoranFaturaServisi::class)->bekleyenFaturaOlustur($adisyon->refresh(), 'e_arsiv');

        $this->assertSame($fatura->id, $tekrar->id);
        $this->assertSame(1, Fatura::withoutGlobalScopes()->count());
        $this->assertSame(FaturaTuru::BekleyenFatura, $fatura->tur);
        $this->assertSame(FaturaDurumu::Beklemede, $fatura->durum);
        $this->assertSame('restoran_adisyon', $fatura->kaynak_tipi);
        $this->assertSame('restoran_satis', $fatura->islem_tipi);
        $this->assertSame((int) $adisyon->id, (int) $fatura->islem_no);
        $this->assertSame('e_arsiv', $fatura->e_belge_tipi);
        $this->assertSame('380.00000000', (string) $fatura->ara_toplam);
        $this->assertSame('20.00000000', (string) $fatura->toplam_indirim);
        $this->assertSame('38.00000000', (string) $fatura->kdv_toplam);
        $this->assertSame('418.00000000', (string) $fatura->genel_toplam);

        $kalem = FaturaKalemi::withoutGlobalScopes()->firstOrFail();
        $this->assertTrue((bool) $kalem->hizmet_mi);
        $this->assertNull($kalem->stok_id);
        $this->assertSame('restoran_adisyon_kalemi', $kalem->kalem_tipi);
        $this->assertSame('418.00000000', (string) $kalem->toplam);
        $this->assertSame(0, StokHareketi::withoutGlobalScopes()->count());
    }

    public function test_acik_restoran_adisyonundan_fatura_olusturulamaz(): void
    {
        $firma = $this->firmaOlustur('RFATACIK');
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_no' => 'AD-FAT-ACIK',
            'acilis_at' => '2026-06-01 14:00:00',
            'durum' => RestoranAdisyonu::DURUM_ACIK,
            'genel_toplam' => 100,
            'para_birimi' => 'TRY',
        ]);

        $this->muhasebeBaglamiHazirla($firma);
        $this->expectException(ValidationException::class);

        app(RestoranFaturaServisi::class)->bekleyenFaturaOlustur($adisyon);
    }

    public function test_aktif_firma_disindaki_adisyondan_fatura_olusturulamaz(): void
    {
        $firmaA = $this->firmaOlustur('RFATA');
        $firmaB = $this->firmaOlustur('RFATB');
        $adisyonB = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firmaB->id,
            'adisyon_no' => 'AD-FAT-FIRMA-B',
            'acilis_at' => '2026-06-01 14:00:00',
            'kapanis_at' => '2026-06-01 15:00:00',
            'durum' => RestoranAdisyonu::DURUM_KAPANDI,
            'genel_toplam' => 100,
            'para_birimi' => 'TRY',
        ]);

        $this->muhasebeBaglamiHazirla($firmaA);
        $this->expectException(ValidationException::class);

        try {
            app(RestoranFaturaServisi::class)->bekleyenFaturaOlustur($adisyonB);
        } finally {
            $this->assertSame(0, Fatura::withoutGlobalScopes()->count());
        }
    }

    public function test_aktif_firma_yokken_adisyonun_kendi_firmasina_fatura_olusturulur(): void
    {
        $firma = $this->firmaOlustur('RFATCLI');
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_no' => 'AD-FAT-CLI',
            'acilis_at' => '2026-06-01 14:00:00',
            'durum' => RestoranAdisyonu::DURUM_ACIK,
            'para_birimi' => 'TRY',
        ]);
        RestoranAdisyonKalemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_id' => $adisyon->id,
            'urun_adi' => 'CLI Yemek',
            'miktar' => 1,
            'birim_fiyat' => 100,
            'kdv_orani' => 0,
        ]);
        $adisyon->refresh()->forceFill([
            'kapanis_at' => '2026-06-01 15:00:00',
            'durum' => RestoranAdisyonu::DURUM_KAPANDI,
        ])->save();

        app(TenantContextService::class)->temizle();

        $fatura = app(RestoranFaturaServisi::class)->bekleyenFaturaOlustur($adisyon->refresh());

        $this->assertSame((int) $firma->id, (int) $fatura->firma_id);
        $this->assertSame('100.00000000', (string) $fatura->genel_toplam);
    }

    public function test_gecersiz_e_belge_tipi_reddedilir(): void
    {
        $firma = $this->firmaOlustur('RFATBELGE');
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_no' => 'AD-FAT-BELGE',
            'acilis_at' => '2026-06-01 15:00:00',
            'kapanis_at' => '2026-06-01 16:00:00',
            'durum' => RestoranAdisyonu::DURUM_KAPANDI,
            'genel_toplam' => 100,
            'para_birimi' => 'TRY',
        ]);

        $this->muhasebeBaglamiHazirla($firma);
        $this->expectException(ValidationException::class);

        app(RestoranFaturaServisi::class)->bekleyenFaturaOlustur($adisyon, 'yanlis');
    }

    public function test_tahsilat_iptalinde_restoran_bekleyen_faturasi_iptal_edilir(): void
    {
        $firma = $this->firmaOlustur('RFATIPTAL');
        $kasa = KasaHesabi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kod' => 'KASA-RFAT-IPTAL',
            'ad' => 'Restoran Fatura Iptal Kasasi',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_no' => 'AD-FAT-IPTAL',
            'acilis_at' => '2026-06-01 17:00:00',
            'durum' => RestoranAdisyonu::DURUM_ACIK,
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
            'para_birimi' => 'TRY',
        ]);
        RestoranAdisyonKalemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_id' => $adisyon->id,
            'urun_adi' => 'Tatli',
            'miktar' => 1,
            'birim_fiyat' => 120,
            'kdv_orani' => 10,
        ]);

        $this->muhasebeBaglamiHazirla($firma);

        app(RestoranTahsilatServisi::class)->adisyonTahsilatiOlustur($adisyon->refresh());
        $fatura = app(RestoranFaturaServisi::class)->bekleyenFaturaOlustur($adisyon->refresh(), 'fatura');
        $tahsilat = RestoranAdisyonTahsilati::withoutGlobalScopes()->firstOrFail();

        app(RestoranTahsilatServisi::class)->tahsilatIptalEt($tahsilat, 'Musteri vazgecti');

        $this->assertSame(FaturaDurumu::Iptal, $fatura->fresh()->durum);
        $this->assertSame('iptal', $fatura->fresh()->odeme_durumu);
        $this->assertSame('0.00000000', (string) $fatura->fresh()->acik_tutar);
        $this->assertSame(RestoranAdisyonu::DURUM_ACIK, $adisyon->fresh()->durum);
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

    private function cariOlustur(Firma $firma, string $kod): Cari
    {
        return Cari::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kod' => $kod,
            'ad' => 'Restoran Musterisi',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
        ]);
    }

    private function muhasebeBaglamiHazirla(Firma $firma): void
    {
        $this->actingAs(User::factory()->create([
            'super_admin_mi' => true,
        ]));
        app(TenantContextService::class)->firmaAyarla($firma);
    }
}
