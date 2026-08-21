<?php

namespace Tests\Feature\PersonelTakip;

use App\Models\Firma;
use App\Models\FirmaKullanici;
use App\Models\Muhasebe\Cari;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelAvansi;
use App\Models\Personel\PersonelGirisCikisi;
use App\Models\Personel\PersonelIzni;
use App\Models\Personel\PersonelMaasDonemi;
use App\Models\Personel\PersonelMaasHareketi;
use App\Models\Personel\PersonelVardiyasi;
use App\Models\Restoran\RestoranAdisyonKalemi;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Sube;
use App\Models\TeknikServis\TeknikServisDurumTanimi;
use App\Models\TeknikServis\TeknikServisGorevAtamasi;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Models\User;
use App\Services\PersonelTakip\PersonelRaporServisi;
use App\TeknikServis\Enumlar\ServisTipi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonelTakipRaporServisiTest extends TestCase
{
    use RefreshDatabase;

    public function test_personel_raporu_firma_ve_sube_bazli_ozet_uretir(): void
    {
        $firma = $this->firmaOlustur('PRS');
        $digerFirma = $this->firmaOlustur('PRD');
        $sube = Sube::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad' => 'Merkez',
            'kod' => 'MRK',
            'aktif_mi' => true,
        ]);
        $kullanici = User::factory()->create();
        FirmaKullanici::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kullanici_id' => $kullanici->id,
            'durum' => 'aktif',
            'onay_durumu' => 'onaylandi',
        ]);

        $personel = Personel::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'kullanici_id' => $kullanici->id,
            'ad_soyad' => 'Rapor Personeli',
            'calisma_tipi' => 'tam_zamanli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 10000,
            'durum' => Personel::DURUM_AKTIF,
        ]);
        Personel::withoutGlobalScopes()->create([
            'firma_id' => $digerFirma->id,
            'ad_soyad' => 'Diger Firma Personeli',
            'calisma_tipi' => 'tam_zamanli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 10000,
            'durum' => Personel::DURUM_AKTIF,
        ]);

        $vardiya = PersonelVardiyasi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'personel_id' => $personel->id,
            'tarih' => '2026-05-10',
            'baslangic_at' => '2026-05-10 10:00:00',
            'bitis_at' => '2026-05-10 18:00:00',
            'mola_dakika' => 30,
            'durum' => 'planlandi',
        ]);
        PersonelGirisCikisi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'personel_id' => $personel->id,
            'vardiya_id' => $vardiya->id,
            'giris_at' => '2026-05-10 10:15:00',
            'cikis_at' => '2026-05-10 19:00:00',
            'onay_durumu' => 'onaylandi',
        ]);
        PersonelIzni::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'izin_turu' => 'yillik_izin',
            'baslangic_at' => '2026-05-12 09:00:00',
            'bitis_at' => '2026-05-12 18:00:00',
            'onay_durumu' => 'onaylandi',
        ]);
        PersonelAvansi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'tarih' => '2026-05-15',
            'tutar' => 1000,
            'kalan_tutar' => 1000,
            'durum' => 'onaylandi',
            'onay_durumu' => 'onaylandi',
        ]);
        $donem = PersonelMaasDonemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'baslangic_tarihi' => '2026-05-01',
            'bitis_tarihi' => '2026-05-31',
        ]);
        PersonelMaasHareketi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'maas_donemi_id' => $donem->id,
            'personel_id' => $personel->id,
            'brut_tutar' => 10000,
            'odenen_tutar' => 4000,
            'durum' => 'onaylandi',
        ]);
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'garson_personel_id' => $personel->id,
            'kasiyer_personel_id' => $personel->id,
            'acilis_at' => '2026-05-10 12:00:00',
            'durum' => RestoranAdisyonu::DURUM_ACIK,
        ]);
        RestoranAdisyonKalemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_id' => $adisyon->id,
            'hazirlayan_personel_id' => $personel->id,
            'urun_adi' => 'Test Urun',
            'miktar' => 2,
            'birim_fiyat' => 60,
            'kdv_orani' => 0,
            'durum' => RestoranAdisyonKalemi::DURUM_HAZIR,
        ]);
        $durum = TeknikServisDurumTanimi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad' => 'Tezgahta',
            'kod' => 'tezgahta',
            'aktif' => true,
        ]);
        $cari = Cari::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kod' => 'CARI-PRS',
            'ad' => 'Rapor Cari',
            'tur' => 'musteri',
            'durum' => 'aktif',
            'para_birimi' => 'TRY',
        ]);
        $servis = TeknikServisKaydi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'servis_tipi' => ServisTipi::ArizaliCihaz->value,
            'oncelik' => 'normal',
            'servis_kanali' => 'magaza',
            'cari_id' => $cari->id,
            'musteri_sikayeti' => 'Test sikayet',
            'kabul_tarihi' => '2026-05-10 09:00:00',
            'fis_no' => 'PRS-TS-001',
            'servis_durumu_id' => $durum->id,
            'olusturan_id' => $personel->kullanici_id,
        ]);
        TeknikServisGorevAtamasi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'teknik_servis_kaydi_id' => $servis->id,
            'atanan_kullanici_id' => $personel->kullanici_id,
            'baslangic_tarihi' => '2026-05-10 10:00:00',
            'bitis_tarihi' => '2026-05-10 18:00:00',
            'durum' => 'tamamlandi',
        ]);

        $rapor = app(PersonelRaporServisi::class)->ozet($firma->id, '2026-05-01', '2026-05-31', $sube->id);

        $this->assertSame(1, $rapor['kpi']['aktif_personel']);
        $this->assertSame(1, $rapor['kpi']['planli_vardiya']);
        $this->assertSame(450, $rapor['kpi']['planli_calisma_dakika']);
        $this->assertSame(525, $rapor['kpi']['fiili_calisma_dakika']);
        $this->assertSame(15, $rapor['kpi']['gec_kalma_dakika']);
        $this->assertSame(60, $rapor['kpi']['fazla_mesai_dakika']);
        $this->assertSame(1000.0, $rapor['kpi']['acik_avans']);
        $this->assertSame(6000.0, $rapor['kpi']['maas_kalan']);
        $this->assertSame('Rapor Personeli', $rapor['personel_performansi'][0]['ad_soyad']);
        $this->assertSame(1, $rapor['kpi']['restoran_adisyon']);
        $this->assertSame(120.0, $rapor['kpi']['restoran_ciro']);
        $this->assertSame('Rapor Personeli', $rapor['restoran_performansi']['garsonlar'][0]['ad_soyad']);
        $this->assertSame(1, $rapor['restoran_performansi']['mutfak'][0]['kalem_sayisi']);
        $this->assertSame(1, $rapor['kpi']['teknik_servis_gorev']);
        $this->assertSame(1, $rapor['kpi']['teknik_servis_tamamlanan_gorev']);
        $this->assertSame('Rapor Personeli', $rapor['teknik_servis_performansi']['personeller'][0]['ad_soyad']);
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
