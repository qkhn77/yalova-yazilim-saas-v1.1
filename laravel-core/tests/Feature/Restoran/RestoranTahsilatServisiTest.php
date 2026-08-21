<?php

namespace Tests\Feature\Restoran;

use App\Models\Firma;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\KasaHareketi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\PosHareketi;
use App\Models\Muhasebe\PosHesabi;
use App\Models\Muhasebe\StokHareketi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Restoran\RestoranAdisyonKalemi;
use App\Models\Restoran\RestoranAdisyonTahsilati;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Restoran\RestoranMenuKategorisi;
use App\Models\Restoran\RestoranMenuUrunu;
use App\Models\Restoran\RestoranReceteKalemi;
use App\Models\Restoran\RestoranRecetesi;
use App\Models\User;
use App\Muhasebe\Enumlar\StokBelgeTuru;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;
use App\Services\Restoran\RestoranTahsilatServisi;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RestoranTahsilatServisiTest extends TestCase
{
    use RefreshDatabase;

    public function test_restoran_adisyonu_kasa_tahsilati_finans_hareketi_olusturur_ve_idempotenttir(): void
    {
        $firma = $this->firmaOlustur('RTK');
        $kasa = KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'KASA',
            'ad' => 'Merkez Kasa',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_no' => 'AD-TEST-1',
            'acilis_at' => '2026-06-01 12:00:00',
            'durum' => RestoranAdisyonu::DURUM_ACIK,
            'genel_toplam' => 450,
            'para_birimi' => 'TRY',
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
        ]);

        $this->muhasebeBaglamiHazirla($firma);

        $finans = app(RestoranTahsilatServisi::class)->adisyonTahsilatiOlustur($adisyon);
        $tekrar = app(RestoranTahsilatServisi::class)->adisyonTahsilatiOlustur($adisyon->refresh());

        $this->assertSame($finans->id, $tekrar->id);
        $this->assertSame('restoran_adisyon', $finans->referans_turu);
        $this->assertSame('Restoran', $finans->modul_etiketi);
        $this->assertSame(RestoranAdisyonu::DURUM_KAPANDI, $adisyon->refresh()->durum);
        $this->assertSame($finans->id, (int) $adisyon->finans_hareketi_id);
        $this->assertSame(1, FinansHareketi::withoutGlobalScopes()->where('referans_turu', 'restoran_adisyon')->count());
        $this->assertSame(1, RestoranAdisyonTahsilati::withoutGlobalScopes()->count());
        $this->assertSame('450.00', (string) KasaHareketi::query()->withoutGlobalScopes()->firstOrFail()->tutar);
    }

    public function test_restoran_adisyonu_parcali_tahsilatla_kapanir(): void
    {
        $firma = $this->firmaOlustur('RTPARC');
        $kasa = KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'KASA-PARC',
            'ad' => 'Parcali Kasa',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);
        $pos = PosHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'POS-PARC',
            'ad' => 'Parcali POS',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_no' => 'AD-PARC-1',
            'acilis_at' => '2026-06-01 17:00:00',
            'durum' => RestoranAdisyonu::DURUM_ACIK,
            'genel_toplam' => 500,
            'para_birimi' => 'TRY',
        ]);

        $this->muhasebeBaglamiHazirla($firma);

        app(RestoranTahsilatServisi::class)->parcaliTahsilatOlustur($adisyon, [
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
            'tutar' => 200,
        ]);

        $this->assertSame(RestoranAdisyonu::DURUM_ODEMEDE, $adisyon->refresh()->durum);
        $this->assertNull($adisyon->finans_hareketi_id);
        $this->assertSame(1, RestoranAdisyonTahsilati::withoutGlobalScopes()->count());
        $this->assertSame('200.00', (string) KasaHareketi::query()->withoutGlobalScopes()->firstOrFail()->tutar);

        app(RestoranTahsilatServisi::class)->parcaliTahsilatOlustur($adisyon->refresh(), [
            'odeme_kanali' => 'pos',
            'pos_hesap_id' => $pos->id,
            'tutar' => 300,
        ]);

        $this->assertSame(RestoranAdisyonu::DURUM_KAPANDI, $adisyon->refresh()->durum);
        $this->assertNotNull($adisyon->finans_hareketi_id);
        $this->assertSame(2, RestoranAdisyonTahsilati::withoutGlobalScopes()->count());
        $this->assertSame(2, FinansHareketi::withoutGlobalScopes()->where('referans_turu', 'restoran_adisyon')->count());
        $this->assertSame('300.00', (string) PosHareketi::query()->withoutGlobalScopes()->firstOrFail()->brut_tutar);
    }

    public function test_restoran_parcali_tahsilati_kalan_tutari_asamaz(): void
    {
        $firma = $this->firmaOlustur('RTKALAN');
        $kasa = KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'KASA-KALAN',
            'ad' => 'Kalan Kasa',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_no' => 'AD-KALAN-1',
            'acilis_at' => '2026-06-01 18:00:00',
            'durum' => RestoranAdisyonu::DURUM_ACIK,
            'genel_toplam' => 400,
            'para_birimi' => 'TRY',
        ]);

        $this->muhasebeBaglamiHazirla($firma);

        app(RestoranTahsilatServisi::class)->parcaliTahsilatOlustur($adisyon, [
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
            'tutar' => 300,
        ]);

        $this->expectException(ValidationException::class);

        app(RestoranTahsilatServisi::class)->parcaliTahsilatOlustur($adisyon->refresh(), [
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
            'tutar' => 200,
        ]);
    }

    public function test_aktif_firma_disindaki_adisyon_tahsil_edilemez(): void
    {
        $firmaA = $this->firmaOlustur('RTFIRMAA');
        $firmaB = $this->firmaOlustur('RTFIRMAB');
        $kasaB = KasaHesabi::query()->create([
            'firma_id' => $firmaB->id,
            'kod' => 'KASA-FIRMA-B',
            'ad' => 'Firma B Kasa',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);
        $adisyonB = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firmaB->id,
            'adisyon_no' => 'AD-FIRMA-B',
            'acilis_at' => '2026-06-01 18:30:00',
            'durum' => RestoranAdisyonu::DURUM_ACIK,
            'genel_toplam' => 100,
            'para_birimi' => 'TRY',
        ]);

        $this->muhasebeBaglamiHazirla($firmaA);
        $this->expectException(ValidationException::class);

        try {
            app(RestoranTahsilatServisi::class)->parcaliTahsilatOlustur($adisyonB, [
                'odeme_kanali' => 'kasa',
                'kasa_hesap_id' => $kasaB->id,
                'tutar' => 100,
            ]);
        } finally {
            $this->assertSame(0, RestoranAdisyonTahsilati::withoutGlobalScopes()->count());
            $this->assertSame(0, FinansHareketi::withoutGlobalScopes()->count());
        }
    }

    public function test_restoran_tahsilati_iptal_edilince_finans_ters_kaydi_olusur(): void
    {
        $firma = $this->firmaOlustur('RTIPTAL');
        $kasa = KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'KASA-IPTAL',
            'ad' => 'Iptal Kasa',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_no' => 'AD-IPTAL-1',
            'acilis_at' => '2026-06-01 19:00:00',
            'durum' => RestoranAdisyonu::DURUM_ACIK,
            'genel_toplam' => 500,
            'para_birimi' => 'TRY',
        ]);

        $this->muhasebeBaglamiHazirla($firma);

        $tahsilat = app(RestoranTahsilatServisi::class)->parcaliTahsilatOlustur($adisyon, [
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
            'tutar' => 200,
        ]);

        $iptal = app(RestoranTahsilatServisi::class)->tahsilatIptalEt($tahsilat, 'Yanlis kasa');
        $tekrar = app(RestoranTahsilatServisi::class)->tahsilatIptalEt($iptal->refresh(), 'Tekrar');

        $this->assertSame($iptal->id, $tekrar->id);
        $this->assertSame(RestoranAdisyonTahsilati::DURUM_IPTAL, $iptal->refresh()->durum);
        $this->assertNotNull($iptal->iptal_finans_hareketi_id);
        $this->assertSame(RestoranAdisyonu::DURUM_ACIK, $adisyon->refresh()->durum);
        $this->assertSame(2, FinansHareketi::withoutGlobalScopes()->count());
        $this->assertSame(2, KasaHareketi::withoutGlobalScopes()->count());
        $this->assertSame('-200.00', (string) KasaHareketi::withoutGlobalScopes()->orderByDesc('id')->firstOrFail()->tutar);
    }

    public function test_restoran_tahsilati_iptal_ve_duzelt_eski_hareketi_baglar(): void
    {
        $firma = $this->firmaOlustur('RTDUZELT');
        $kasa = KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'KASA-DUZELT',
            'ad' => 'Düzeltme Kasa',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_no' => 'AD-DUZELT-1',
            'acilis_at' => '2026-06-01 20:00:00',
            'durum' => RestoranAdisyonu::DURUM_ACIK,
            'genel_toplam' => 500,
            'para_birimi' => 'TRY',
        ]);

        $this->muhasebeBaglamiHazirla($firma);
        $eski = app(RestoranTahsilatServisi::class)->parcaliTahsilatOlustur($adisyon, [
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
            'tutar' => 200,
        ]);

        $yeni = app(RestoranTahsilatServisi::class)->tahsilatIptalEtVeDuzelt($eski, [
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
            'tutar' => 250,
        ], 'Tutar düzeltmesi');

        $this->assertSame(RestoranAdisyonTahsilati::DURUM_IPTAL, $eski->refresh()->durum);
        $this->assertSame(RestoranAdisyonTahsilati::DURUM_AKTIF, $yeni->durum);
        $this->assertSame('250.00', (string) $yeni->tutar);
        $this->assertSame((int) $eski->finans_hareketi_id, (int) $yeni->finansHareketi->duzeltme_kaynagi_id);
        $this->assertSame('iptal', $eski->finansHareketi->refresh()->durum->value);
        $this->assertSame('aktif', $yeni->finansHareketi->refresh()->durum->value);
    }

    public function test_aktif_firma_disindaki_tahsilat_iptal_edilemez(): void
    {
        $firmaA = $this->firmaOlustur('RTIPTALA');
        $firmaB = $this->firmaOlustur('RTIPTALB');
        $kasaB = KasaHesabi::query()->create([
            'firma_id' => $firmaB->id,
            'kod' => 'KASA-IPTAL-B',
            'ad' => 'Firma B Iptal Kasa',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);
        $adisyonB = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firmaB->id,
            'adisyon_no' => 'AD-IPTAL-B',
            'acilis_at' => '2026-06-01 18:45:00',
            'durum' => RestoranAdisyonu::DURUM_ACIK,
            'genel_toplam' => 100,
            'para_birimi' => 'TRY',
        ]);

        $this->muhasebeBaglamiHazirla($firmaB);
        $tahsilat = app(RestoranTahsilatServisi::class)->parcaliTahsilatOlustur($adisyonB, [
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $kasaB->id,
            'tutar' => 100,
        ]);

        $this->muhasebeBaglamiHazirla($firmaA);
        $this->expectException(ValidationException::class);

        try {
            app(RestoranTahsilatServisi::class)->tahsilatIptalEt($tahsilat, 'Firma disi deneme');
        } finally {
            $this->assertSame(RestoranAdisyonTahsilati::DURUM_AKTIF, $tahsilat->fresh()->durum);
            $this->assertSame(1, FinansHareketi::withoutGlobalScopes()->count());
            $this->assertSame(1, KasaHareketi::withoutGlobalScopes()->count());
        }
    }

    public function test_kapali_restoran_adisyon_tahsilati_iptal_edilince_stok_iade_edilir(): void
    {
        $firma = $this->firmaOlustur('RTSTOKIADE');
        $kasa = KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'KASA-STOK-IADE',
            'ad' => 'Stok Iade Kasa',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);
        $stok = StokKarti::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kod' => 'REST-STOK-IADE',
            'ad' => 'Restoran Stok Iade Urunu',
            'tur' => 'ticari_mal',
            'birim' => 'AD',
            'satis_fiyati' => 100,
            'kdv_orani' => 0,
            'stok_takip' => true,
            'stok_miktari' => 5,
            'guncel_birim_maliyet' => 40,
            'stok_degeri' => 200,
            'durum' => 'aktif',
        ]);
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_no' => 'AD-STOK-IADE-1',
            'acilis_at' => '2026-06-01 20:00:00',
            'durum' => RestoranAdisyonu::DURUM_ACIK,
            'para_birimi' => 'TRY',
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
        ]);
        RestoranAdisyonKalemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_id' => $adisyon->id,
            'stok_karti_id' => $stok->id,
            'urun_adi' => 'Restoran Stok Iade Urunu',
            'miktar' => 2,
            'birim_fiyat' => 100,
            'kdv_orani' => 0,
        ]);

        $this->muhasebeBaglamiHazirla($firma);

        app(RestoranTahsilatServisi::class)->adisyonTahsilatiOlustur($adisyon->refresh());
        $tahsilat = RestoranAdisyonTahsilati::withoutGlobalScopes()->firstOrFail();

        $this->assertSame('3.00000000', (string) $stok->refresh()->stok_miktari);

        app(RestoranTahsilatServisi::class)->tahsilatIptalEt($tahsilat, 'Musteri vazgecti');

        $this->assertSame(RestoranAdisyonu::DURUM_ACIK, $adisyon->refresh()->durum);
        $this->assertSame('5.00000000', (string) $stok->refresh()->stok_miktari);
        $this->assertSame(2, StokHareketi::withoutGlobalScopes()->count());
        $this->assertSame(StokHareketIslemTuru::Alis, StokHareketi::withoutGlobalScopes()->orderByDesc('id')->firstOrFail()->islem_turu);
    }

    public function test_restoran_pos_tahsilati_pos_hareketi_olusturur(): void
    {
        $firma = $this->firmaOlustur('RTP');
        $pos = PosHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'POS',
            'ad' => 'Merkez POS',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_no' => 'AD-TEST-POS',
            'acilis_at' => '2026-06-01 13:00:00',
            'durum' => RestoranAdisyonu::DURUM_ACIK,
            'genel_toplam' => 300,
            'para_birimi' => 'TRY',
            'odeme_kanali' => 'pos',
            'pos_hesap_id' => $pos->id,
        ]);

        $this->muhasebeBaglamiHazirla($firma);

        app(RestoranTahsilatServisi::class)->adisyonTahsilatiOlustur($adisyon);

        $this->assertSame('300.00', (string) PosHareketi::query()->withoutGlobalScopes()->firstOrFail()->brut_tutar);
    }

    public function test_restoran_tahsilatinda_hesap_firma_ve_para_birimi_uyumlu_olmalidir(): void
    {
        $firma = $this->firmaOlustur('RTF');
        $digerFirma = $this->firmaOlustur('RTD');
        $banka = BankaHesabi::query()->create([
            'firma_id' => $digerFirma->id,
            'kod' => 'BANKA',
            'ad' => 'Diger Banka',
            'para_birimi' => 'USD',
            'durum' => 'aktif',
        ]);
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_no' => 'AD-TEST-BANKA',
            'acilis_at' => '2026-06-01 14:00:00',
            'durum' => RestoranAdisyonu::DURUM_ACIK,
            'genel_toplam' => 100,
            'para_birimi' => 'TRY',
            'odeme_kanali' => 'banka',
            'banka_hesap_id' => $banka->id,
        ]);

        $this->muhasebeBaglamiHazirla($firma);

        $this->expectException(ValidationException::class);

        app(RestoranTahsilatServisi::class)->adisyonTahsilatiOlustur($adisyon);
    }

    public function test_restoran_tahsilati_stok_hareketi_olusturur_ve_idempotenttir(): void
    {
        $firma = $this->firmaOlustur('RTS');
        $kasa = KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'KASA-STOK',
            'ad' => 'Stok Kasa',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);
        $stok = StokKarti::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kod' => 'REST-STOK-1',
            'ad' => 'Restoran Stok Urunu',
            'tur' => 'ticari_mal',
            'birim' => 'AD',
            'satis_fiyati' => 100,
            'kdv_orani' => 10,
            'stok_takip' => true,
            'stok_miktari' => 5,
            'guncel_birim_maliyet' => 40,
            'stok_degeri' => 200,
            'durum' => 'aktif',
        ]);
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_no' => 'AD-STOK-1',
            'acilis_at' => '2026-06-01 15:00:00',
            'durum' => RestoranAdisyonu::DURUM_ACIK,
            'para_birimi' => 'TRY',
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
        ]);
        RestoranAdisyonKalemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_id' => $adisyon->id,
            'stok_karti_id' => $stok->id,
            'urun_adi' => 'Restoran Stok Urunu',
            'miktar' => 2,
            'birim_fiyat' => 100,
            'kdv_orani' => 10,
        ]);

        $this->muhasebeBaglamiHazirla($firma);

        app(RestoranTahsilatServisi::class)->adisyonTahsilatiOlustur($adisyon->refresh());
        app(RestoranTahsilatServisi::class)->adisyonTahsilatiOlustur($adisyon->refresh());

        $hareket = StokHareketi::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(1, StokHareketi::withoutGlobalScopes()->count());
        $this->assertSame(StokBelgeTuru::RestoranAdisyon, $hareket->belge_turu);
        $this->assertSame(StokHareketIslemTuru::Satis, $hareket->islem_turu);
        $this->assertSame('2.00000000', (string) $hareket->miktar);
        $this->assertSame('3.00000000', (string) $stok->refresh()->stok_miktari);
    }

    public function test_restoran_tahsilati_yetersiz_stokta_finans_kaydi_birakmadan_engellenir(): void
    {
        $firma = $this->firmaOlustur('RTSTOKYOK');
        $kasa = KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'KASA-STOK-YOK',
            'ad' => 'Stok Kontrol Kasa',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);
        $stok = StokKarti::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kod' => 'REST-STOK-YOK',
            'ad' => 'Az Stoklu Urun',
            'tur' => 'ticari_mal',
            'birim' => 'AD',
            'satis_fiyati' => 100,
            'kdv_orani' => 10,
            'stok_takip' => true,
            'stok_miktari' => 1,
            'guncel_birim_maliyet' => 40,
            'stok_degeri' => 40,
            'durum' => 'aktif',
        ]);
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_no' => 'AD-STOK-YOK-1',
            'acilis_at' => '2026-06-01 15:30:00',
            'durum' => RestoranAdisyonu::DURUM_ACIK,
            'para_birimi' => 'TRY',
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
        ]);
        RestoranAdisyonKalemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_id' => $adisyon->id,
            'stok_karti_id' => $stok->id,
            'urun_adi' => 'Az Stoklu Urun',
            'miktar' => 2,
            'birim_fiyat' => 100,
            'kdv_orani' => 10,
        ]);

        $this->muhasebeBaglamiHazirla($firma);

        try {
            app(RestoranTahsilatServisi::class)->adisyonTahsilatiOlustur($adisyon->refresh());
            $this->fail('Yetersiz stok validasyonu bekleniyordu.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Yetersiz stok', $exception->errors()['stok'][0] ?? '');
        }

        $this->assertSame(RestoranAdisyonu::DURUM_ACIK, $adisyon->refresh()->durum);
        $this->assertSame('1.00000000', (string) $stok->refresh()->stok_miktari);
        $this->assertSame(0, FinansHareketi::withoutGlobalScopes()->count());
        $this->assertSame(0, KasaHareketi::withoutGlobalScopes()->count());
        $this->assertSame(0, RestoranAdisyonTahsilati::withoutGlobalScopes()->count());
        $this->assertSame(0, StokHareketi::withoutGlobalScopes()->count());
    }

    public function test_receteli_menu_urunu_satista_malzeme_stoklarini_duser(): void
    {
        $firma = $this->firmaOlustur('RTR');
        $kasa = KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'KASA-RECETE',
            'ad' => 'Recete Kasa',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);
        $urunStok = StokKarti::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kod' => 'MENU-BURGER',
            'ad' => 'Burger Satis Urunu',
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
        $malzemeStok = StokKarti::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kod' => 'MALZEME-KOFTE',
            'ad' => 'Kofte',
            'tur' => 'hammadde',
            'birim' => 'KG',
            'satis_fiyati' => 0,
            'kdv_orani' => 0,
            'stok_takip' => true,
            'stok_miktari' => 5,
            'guncel_birim_maliyet' => 100,
            'stok_degeri' => 500,
            'durum' => 'aktif',
        ]);
        $kategori = RestoranMenuKategorisi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad' => 'Ana Yemek',
        ]);
        $menuUrunu = RestoranMenuUrunu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kategori_id' => $kategori->id,
            'stok_karti_id' => $urunStok->id,
            'ad' => 'Burger',
            'fiyat' => 200,
            'kdv_orani' => 10,
        ]);
        $recete = RestoranRecetesi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'menu_urunu_id' => $menuUrunu->id,
            'ad' => 'Burger recetesi',
        ]);
        RestoranReceteKalemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'recete_id' => $recete->id,
            'stok_karti_id' => $malzemeStok->id,
            'miktar' => 0.25,
            'fire_orani' => 10,
        ]);
        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_no' => 'AD-RECETE-1',
            'acilis_at' => '2026-06-01 16:00:00',
            'durum' => RestoranAdisyonu::DURUM_ACIK,
            'para_birimi' => 'TRY',
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
        ]);
        RestoranAdisyonKalemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_id' => $adisyon->id,
            'menu_urunu_id' => $menuUrunu->id,
            'stok_karti_id' => $urunStok->id,
            'urun_adi' => 'Burger',
            'miktar' => 2,
            'birim_fiyat' => 200,
            'kdv_orani' => 10,
        ]);

        $this->muhasebeBaglamiHazirla($firma);

        app(RestoranTahsilatServisi::class)->adisyonTahsilatiOlustur($adisyon->refresh());

        $this->assertSame(1, StokHareketi::withoutGlobalScopes()->count());
        $hareket = StokHareketi::withoutGlobalScopes()->firstOrFail();
        $this->assertSame((int) $malzemeStok->id, (int) $hareket->stok_id);
        $this->assertSame('0.55000000', (string) $hareket->miktar);
        $this->assertSame('4.45000000', (string) $malzemeStok->refresh()->stok_miktari);
        $this->assertSame('10.00000000', (string) $urunStok->refresh()->stok_miktari);
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

    private function muhasebeBaglamiHazirla(Firma $firma): void
    {
        $this->actingAs(User::factory()->create([
            'super_admin_mi' => true,
        ]));
        app(TenantContextService::class)->firmaAyarla($firma);
    }
}
