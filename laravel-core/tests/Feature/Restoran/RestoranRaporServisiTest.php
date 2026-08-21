<?php

namespace Tests\Feature\Restoran;

use App\Models\Firma;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\StokHareketi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Personel\Personel;
use App\Models\Restoran\RestoranAdisyonKalemi;
use App\Models\Restoran\RestoranAdisyonTahsilati;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Sube;
use App\Muhasebe\Enumlar\StokBelgeTuru;
use App\Muhasebe\Enumlar\FinansHareketDurumu;
use App\Muhasebe\Enumlar\FinansHareketTuru;
use App\Muhasebe\Enumlar\StokHareketDurumu;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;
use App\Services\Restoran\RestoranRaporServisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestoranRaporServisiTest extends TestCase
{
    use RefreshDatabase;

    public function test_gunluk_ozet_firma_ve_tarih_bazli_hesaplanir(): void
    {
        $firma = $this->firmaOlustur('RRG');
        $digerFirma = $this->firmaOlustur('RRD');
        $sube = $this->subeOlustur($firma, 'MRK');
        $bugun = now()->startOfDay()->addHours(12);

        $this->adisyonOlustur($firma, $sube, [
            'siparis_tipi' => 'masa',
            'durum' => RestoranAdisyonu::DURUM_KAPANDI,
            'acilis_at' => $bugun,
            'kapanis_at' => $bugun->copy()->addHour(),
            'genel_toplam' => 300,
        ]);
        $this->adisyonOlustur($firma, $sube, [
            'siparis_tipi' => 'paket',
            'acilis_at' => $bugun,
            'genel_toplam' => 120,
        ]);
        $this->adisyonOlustur($digerFirma, null, [
            'siparis_tipi' => 'masa',
            'acilis_at' => $bugun,
            'genel_toplam' => 999,
        ]);

        $ozet = app(RestoranRaporServisi::class)->gunlukOzet((int) $firma->id, $bugun);

        $this->assertSame(2, $ozet['adisyon_sayisi']);
        $this->assertSame(1, $ozet['kapali_adisyon_sayisi']);
        $this->assertSame(1, $ozet['masa_adisyon_sayisi']);
        $this->assertSame(1, $ozet['paket_adisyon_sayisi']);
        $this->assertSame(420.0, $ozet['toplam_tutar']);
        $this->assertSame(300.0, $ozet['tahsil_edilen_tutar']);
    }

    public function test_personel_performans_raporlari_hesaplanir(): void
    {
        $firma = $this->firmaOlustur('RRP');
        $sube = $this->subeOlustur($firma, 'MRK');
        $garson = $this->personelOlustur($firma, $sube, 'Garson');
        $kasiyer = $this->personelOlustur($firma, $sube, 'Kasiyer');
        $kurye = $this->personelOlustur($firma, $sube, 'Kurye');
        $mutfak = $this->personelOlustur($firma, $sube, 'Mutfak');
        $bugun = now()->startOfDay()->addHours(14);

        $masaAdisyon = $this->adisyonOlustur($firma, $sube, [
            'garson_personel_id' => $garson->id,
            'kasiyer_personel_id' => $kasiyer->id,
            'siparis_tipi' => 'masa',
            'acilis_at' => $bugun,
            'genel_toplam' => 250,
        ]);
        $paketAdisyon = $this->adisyonOlustur($firma, $sube, [
            'kurye_personel_id' => $kurye->id,
            'siparis_tipi' => 'paket',
            'paket_durum' => RestoranAdisyonu::PAKET_DURUM_TESLIM_EDILDI,
            'acilis_at' => $bugun,
            'teslimat_at' => $bugun->copy()->addHours(2),
            'genel_toplam' => 150,
        ]);
        $this->kalemOlustur($firma, $masaAdisyon, $mutfak, 'Kebap', 250);
        RestoranAdisyonTahsilati::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_id' => $masaAdisyon->id,
            'finans_hareketi_id' => $this->finansHareketiOlustur($firma, $masaAdisyon, 100, $bugun)->id,
            'odeme_kanali' => 'kasa',
            'tutar' => 100,
            'para_birimi' => 'TRY',
            'tahsilat_at' => $bugun,
            'durum' => RestoranAdisyonTahsilati::DURUM_AKTIF,
        ]);
        RestoranAdisyonTahsilati::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_id' => $masaAdisyon->id,
            'finans_hareketi_id' => $this->finansHareketiOlustur($firma, $masaAdisyon, 150, $bugun->copy()->addMinutes(5))->id,
            'odeme_kanali' => 'pos',
            'tutar' => 150,
            'para_birimi' => 'TRY',
            'tahsilat_at' => $bugun->copy()->addMinutes(5),
            'durum' => RestoranAdisyonTahsilati::DURUM_AKTIF,
        ]);
        $this->kalemOlustur($firma, $paketAdisyon, $mutfak, 'Tatlı', 150);

        $servis = app(RestoranRaporServisi::class);

        $garsonRaporu = $servis->garsonPerformansi((int) $firma->id, $bugun, $bugun);
        $kasiyerRaporu = $servis->kasiyerPerformansi((int) $firma->id, $bugun, $bugun);
        $kuryeRaporu = $servis->kuryePerformansi((int) $firma->id, $bugun, $bugun);
        $mutfakRaporu = $servis->mutfakPerformansi((int) $firma->id, $bugun, $bugun);

        $this->assertSame((int) $garson->id, (int) $garsonRaporu->first()->garson_personel_id);
        $this->assertSame(1, (int) $garsonRaporu->first()->adisyon_sayisi);
        $this->assertSame('250', (string) $garsonRaporu->first()->toplam_tutar);

        $this->assertSame((int) $kasiyer->id, (int) $kasiyerRaporu->first()->kasiyer_personel_id);
        $this->assertSame(2, (int) $kasiyerRaporu->first()->tahsilat_sayisi);
        $this->assertSame(1, (int) $kasiyerRaporu->first()->adisyon_sayisi);
        $this->assertSame('250', (string) $kasiyerRaporu->first()->tahsilat_tutari);

        $this->assertSame((int) $kurye->id, (int) $kuryeRaporu->first()->kurye_personel_id);
        $this->assertSame(1, (int) $kuryeRaporu->first()->teslimat_sayisi);

        $this->assertSame((int) $mutfak->id, (int) $mutfakRaporu->first()->hazirlayan_personel_id);
        $this->assertSame(2, (int) $mutfakRaporu->first()->kalem_sayisi);
    }

    public function test_paket_operasyon_ozeti_hesaplanir(): void
    {
        $firma = $this->firmaOlustur('RRO');
        $sube = $this->subeOlustur($firma, 'MRK');
        $bugun = now()->startOfDay()->addHours(12);

        $this->adisyonOlustur($firma, $sube, [
            'siparis_tipi' => 'paket',
            'paket_durum' => RestoranAdisyonu::PAKET_DURUM_TESLIM_EDILDI,
            'acilis_at' => $bugun,
            'tahmini_teslimat_at' => $bugun->copy()->addMinutes(30),
            'teslimat_at' => $bugun->copy()->addMinutes(45),
            'genel_toplam' => 200,
        ]);
        $this->adisyonOlustur($firma, $sube, [
            'siparis_tipi' => 'online',
            'paket_durum' => RestoranAdisyonu::PAKET_DURUM_YOLDA,
            'acilis_at' => $bugun,
            'tahmini_teslimat_at' => now()->subMinutes(10),
            'genel_toplam' => 100,
        ]);
        $this->adisyonOlustur($firma, $sube, [
            'siparis_tipi' => 'masa',
            'acilis_at' => $bugun,
            'genel_toplam' => 999,
        ]);

        $ozet = app(RestoranRaporServisi::class)->paketOperasyonOzeti((int) $firma->id, $bugun, $bugun);

        $this->assertSame(2, $ozet['siparis_sayisi']);
        $this->assertSame(1, $ozet['yolda_sayisi']);
        $this->assertSame(1, $ozet['teslim_edildi_sayisi']);
        $this->assertSame(2, $ozet['geciken_sayisi']);
        $this->assertSame(45.0, $ozet['ortalama_teslimat_dakika']);
        $this->assertSame(300.0, $ozet['toplam_tutar']);
    }

    public function test_tahsilat_kanal_ozeti_firma_tarih_ve_para_birimi_bazli_hesaplanir(): void
    {
        $firma = $this->firmaOlustur('RRT');
        $digerFirma = $this->firmaOlustur('RRX');
        $bugun = now()->startOfDay()->addHours(20);
        $adisyon = $this->adisyonOlustur($firma, null, [
            'siparis_tipi' => 'masa',
            'acilis_at' => $bugun,
            'genel_toplam' => 250,
        ]);
        $digerAdisyon = $this->adisyonOlustur($digerFirma, null, [
            'siparis_tipi' => 'masa',
            'acilis_at' => $bugun,
            'genel_toplam' => 999,
        ]);

        RestoranAdisyonTahsilati::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_id' => $adisyon->id,
            'finans_hareketi_id' => $this->finansHareketiOlustur($firma, $adisyon, 120, $bugun)->id,
            'odeme_kanali' => 'kasa',
            'tutar' => 120,
            'para_birimi' => 'TRY',
            'tahsilat_at' => $bugun,
            'durum' => RestoranAdisyonTahsilati::DURUM_AKTIF,
        ]);
        RestoranAdisyonTahsilati::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_id' => $adisyon->id,
            'finans_hareketi_id' => $this->finansHareketiOlustur($firma, $adisyon, 80, $bugun->copy()->addMinutes(10))->id,
            'odeme_kanali' => 'kasa',
            'tutar' => 80,
            'para_birimi' => 'TRY',
            'tahsilat_at' => $bugun->copy()->addMinutes(10),
            'durum' => RestoranAdisyonTahsilati::DURUM_AKTIF,
        ]);
        RestoranAdisyonTahsilati::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_id' => $adisyon->id,
            'finans_hareketi_id' => $this->finansHareketiOlustur($firma, $adisyon, 50, $bugun)->id,
            'odeme_kanali' => 'pos',
            'tutar' => 50,
            'para_birimi' => 'TRY',
            'tahsilat_at' => $bugun,
            'durum' => RestoranAdisyonTahsilati::DURUM_AKTIF,
        ]);
        RestoranAdisyonTahsilati::withoutGlobalScopes()->create([
            'firma_id' => $digerFirma->id,
            'adisyon_id' => $digerAdisyon->id,
            'finans_hareketi_id' => $this->finansHareketiOlustur($digerFirma, $digerAdisyon, 999, $bugun)->id,
            'odeme_kanali' => 'kasa',
            'tutar' => 999,
            'para_birimi' => 'TRY',
            'tahsilat_at' => $bugun,
            'durum' => RestoranAdisyonTahsilati::DURUM_AKTIF,
        ]);
        RestoranAdisyonTahsilati::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_id' => $adisyon->id,
            'finans_hareketi_id' => $this->finansHareketiOlustur($firma, $adisyon, 500, $bugun->copy()->subDay())->id,
            'odeme_kanali' => 'banka',
            'tutar' => 500,
            'para_birimi' => 'TRY',
            'tahsilat_at' => $bugun->copy()->subDay(),
            'durum' => RestoranAdisyonTahsilati::DURUM_AKTIF,
        ]);

        $ozet = app(RestoranRaporServisi::class)->tahsilatKanalOzeti((int) $firma->id, $bugun, $bugun);
        $kasa = $ozet->firstWhere('odeme_kanali', 'kasa');
        $pos = $ozet->firstWhere('odeme_kanali', 'pos');

        $this->assertCount(2, $ozet);
        $this->assertSame(2, (int) $kasa->tahsilat_sayisi);
        $this->assertSame(200.0, (float) $kasa->toplam_tutar);
        $this->assertSame(1, (int) $pos->tahsilat_sayisi);
        $this->assertSame(50.0, (float) $pos->toplam_tutar);
    }

    public function test_urun_satis_ve_stok_karlilik_ozeti_hesaplanir(): void
    {
        $firma = $this->firmaOlustur('RRK');
        $sube = $this->subeOlustur($firma, 'MRK');
        $bugun = now()->startOfDay()->addHours(18);
        $adisyon = $this->adisyonOlustur($firma, $sube, [
            'siparis_tipi' => 'masa',
            'durum' => RestoranAdisyonu::DURUM_ACIK,
            'acilis_at' => $bugun,
        ]);
        $stok = StokKarti::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kod' => 'RRK-STOK',
            'ad' => 'Rapor Stok',
            'tur' => 'ticari_mal',
            'birim' => 'AD',
            'stok_takip' => true,
            'stok_miktari' => 10,
            'guncel_birim_maliyet' => 30,
            'stok_degeri' => 300,
            'durum' => 'aktif',
        ]);

        RestoranAdisyonKalemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_id' => $adisyon->id,
            'stok_karti_id' => $stok->id,
            'urun_adi' => 'Burger',
            'miktar' => 2,
            'birim_fiyat' => 100,
            'kdv_orani' => 0,
        ]);
        $adisyon->forceFill([
            'durum' => RestoranAdisyonu::DURUM_KAPANDI,
            'tahsilat_at' => $bugun->copy()->addHour(),
        ])->save();
        StokHareketi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'stok_id' => $stok->id,
            'islem_turu' => StokHareketIslemTuru::Satis,
            'miktar' => 2,
            'onceki_miktar' => 10,
            'sonraki_miktar' => 8,
            'birim_fiyat' => 100,
            'birim_maliyet' => 30,
            'toplam' => 200,
            'toplam_maliyet' => 60,
            'belge_turu' => StokBelgeTuru::RestoranAdisyon,
            'referans_tipi' => StokBelgeTuru::RestoranAdisyon->value,
            'belge_id' => $adisyon->id,
            'referans_id' => $adisyon->id,
            'tarih' => $bugun,
            'islem_tarihi' => $bugun,
            'durum' => StokHareketDurumu::Aktif,
        ]);

        $servis = app(RestoranRaporServisi::class);
        $urunler = $servis->urunSatisOzeti((int) $firma->id, $bugun, $bugun);
        $karlilik = $servis->stokKarlilikOzeti((int) $firma->id, $bugun, $bugun);

        $this->assertSame('Burger', $urunler->first()->urun_adi);
        $this->assertSame('2', (string) $urunler->first()->toplam_miktar);
        $this->assertSame('200.00', (string) $urunler->first()->toplam_tutar);
        $this->assertSame(200.0, $karlilik['satis_tutari']);
        $this->assertSame(60.0, $karlilik['stok_maliyeti']);
        $this->assertSame(140.0, $karlilik['brut_kar']);
        $this->assertSame(70.0, $karlilik['brut_kar_orani']);
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

    private function finansHareketiOlustur(Firma $firma, RestoranAdisyonu $adisyon, float $tutar, mixed $tarih): FinansHareketi
    {
        return FinansHareketi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'tur' => FinansHareketTuru::Tahsilat->value,
            'tarih' => $tarih,
            'vade_tarihi' => null,
            'tutar' => $tutar,
            'baz_tutar' => $tutar,
            'para_birimi' => 'TRY',
            'baz_para_birimi' => 'TRY',
            'kur' => 1,
            'cari_id' => null,
            'aciklama' => 'Restoran test tahsilati',
            'referans_turu' => 'restoran_adisyon',
            'referans_id' => $adisyon->id,
            'durum' => FinansHareketDurumu::Aktif->value,
            'iptal_edilen_hareket_id' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $ekler
     */
    private function adisyonOlustur(Firma $firma, ?Sube $sube, array $ekler): RestoranAdisyonu
    {
        return RestoranAdisyonu::withoutGlobalScopes()->create(array_merge([
            'firma_id' => $firma->id,
            'sube_id' => $sube?->id,
        ], $ekler));
    }

    private function kalemOlustur(
        Firma $firma,
        RestoranAdisyonu $adisyon,
        Personel $hazirlayan,
        string $urunAdi,
        float $toplamTutar
    ): RestoranAdisyonKalemi {
        return RestoranAdisyonKalemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_id' => $adisyon->id,
            'hazirlayan_personel_id' => $hazirlayan->id,
            'urun_adi' => $urunAdi,
            'miktar' => 1,
            'birim_fiyat' => $toplamTutar,
            'kdv_orani' => 0,
            'durum' => RestoranAdisyonKalemi::DURUM_HAZIR,
        ]);
    }
}
