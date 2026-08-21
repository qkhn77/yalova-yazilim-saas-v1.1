<?php

namespace Tests\Feature\Analytics;

use App\Models\Ecommerce\Odeme;
use App\Models\Ecommerce\Siparis;
use App\Models\Ecommerce\SiparisKalemi;
use App\Models\Firma;
use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokKategorisi;
use App\Models\SistemOlayi;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Services\IsAnalitikServisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IsAnalitikServisiTest extends TestCase
{
    use RefreshDatabase;

    private function firma(): Firma
    {
        return Firma::query()->create([
            'ad' => 'Analitik Firma',
            'kisa_ad' => 'ANL-'.uniqid(),
            'firma_kodu' => 'ANLK-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
    }

    private function stokOlustur(Firma $firma, string $kod, int $goruntulenme = 0, float $stokMiktari = 10, float $minimum = 2): StokKarti
    {
        $kategori = StokKategorisi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'KTG-'.uniqid(),
            'ad' => 'Kategori '.uniqid(),
            'aktif_mi' => true,
            'is_sabit' => false,
        ]);
        $birim = Birim::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'AD-'.uniqid(),
            'ad' => 'Adet '.uniqid(),
            'aktif_mi' => true,
            'is_sabit' => true,
        ]);

        return StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => $kod,
            'ad' => 'Urun '.$kod,
            'slug' => 'urun-'.$kod.'-'.uniqid(),
            'tur' => StokKartiTuru::ETicaret->value,
            'durum' => HesapDurumu::Aktif->value,
            'kategori_id' => $kategori->id,
            'kategori_kodu' => $kategori->kod,
            'birim' => $birim->kod,
            'satis_fiyati' => 100,
            'kdv_orani' => 0,
            'stok_takip' => true,
            'stok_miktari' => $stokMiktari,
            'minimum_stok' => $minimum,
            'rezerve_miktar' => 0,
            'goruntulenme_sayisi' => $goruntulenme,
        ]);
    }

    private function siparisOlustur(Firma $firma, string $no, string $durum, float $genelToplam, string $pb = 'TRY'): Siparis
    {
        return Siparis::query()->create([
            'siparis_no' => $no,
            'firma_id' => $firma->id,
            'musteri_ad_soyad' => 'Test Kullanici',
            'musteri_email' => 'test@example.com',
            'musteri_telefon' => '05000000000',
            'teslimat_adresi' => 'Adres',
            'para_birimi' => $pb,
            'ara_toplam' => $genelToplam,
            'kdv_toplam' => 0,
            'genel_toplam' => $genelToplam,
            'durum' => $durum,
            'stok_dusuldu_mi' => false,
        ]);
    }

    public function test_kpi_verileri_dogru_hesaplanir(): void
    {
        $firma = $this->firma();
        $this->siparisOlustur($firma, 'S-1', Siparis::DURUM_ONAYLANDI_YENI, 100);
        $this->siparisOlustur($firma, 'S-2', Siparis::DURUM_ONAY_BEKLIYOR, 50);

        $data = app(IsAnalitikServisi::class)->olustur($firma->id);

        $this->assertSame(2, (int) $data['kpi']['bugun_siparis']);
        $this->assertSame('100.00', (string) $data['kpi']['bugun_ciro_pb']['TRY']);
    }

    public function test_odeme_basarisi_ve_iptal_orani_dogru_hesaplanir(): void
    {
        $firma = $this->firma();
        $s1 = $this->siparisOlustur($firma, 'S-3', Siparis::DURUM_ONAYLANDI_YENI, 100);
        $s2 = $this->siparisOlustur($firma, 'S-4', Siparis::DURUM_IPTAL_EDILDI, 90);

        Odeme::query()->create(['siparis_id' => $s1->id, 'odeme_no' => 'O-1', 'tutar' => 100, 'para_birimi' => 'TRY', 'durum' => Odeme::DURUM_BASARILI]);
        Odeme::query()->create(['siparis_id' => $s2->id, 'odeme_no' => 'O-2', 'tutar' => 90, 'para_birimi' => 'TRY', 'durum' => Odeme::DURUM_BASARISIZ]);

        $data = app(IsAnalitikServisi::class)->olustur($firma->id);
        $this->assertSame(50.0, (float) $data['kpi']['odeme_basarili_orani']);
        $this->assertSame(50.0, (float) $data['kpi']['iptal_orani']);
    }

    public function test_en_cok_satan_urunler_dogru_listelenir(): void
    {
        $firma = $this->firma();
        $s = $this->siparisOlustur($firma, 'S-5', Siparis::DURUM_ONAYLANDI_YENI, 300);
        $stokA = $this->stokOlustur($firma, 'A1', 10);
        $stokB = $this->stokOlustur($firma, 'B1', 5);

        SiparisKalemi::query()->create([
            'siparis_id' => $s->id,
            'stok_karti_id' => $stokA->id,
            'urun_adi_snapshot' => 'Urun A',
            'urun_kodu_snapshot' => 'A1',
            'miktar' => 3,
            'birim_fiyat' => 50,
            'kdv_orani' => 0,
            'satir_toplami' => 150,
        ]);
        SiparisKalemi::query()->create([
            'siparis_id' => $s->id,
            'stok_karti_id' => $stokB->id,
            'urun_adi_snapshot' => 'Urun B',
            'urun_kodu_snapshot' => 'B1',
            'miktar' => 1,
            'birim_fiyat' => 100,
            'kdv_orani' => 0,
            'satir_toplami' => 100,
        ]);

        $data = app(IsAnalitikServisi::class)->olustur($firma->id);
        $first = $data['listeler']['en_cok_satanlar'][0] ?? null;
        $this->assertNotNull($first);
        $this->assertSame('Urun A', $first['urun_adi']);
    }

    public function test_para_birimi_karisiklikta_tek_toplam_yapmaz(): void
    {
        $firma = $this->firma();
        $this->siparisOlustur($firma, 'S-6', Siparis::DURUM_ONAYLANDI_YENI, 100, 'TRY');
        $this->siparisOlustur($firma, 'S-7', Siparis::DURUM_ONAYLANDI_YENI, 10, 'USD');

        $data = app(IsAnalitikServisi::class)->olustur($firma->id);
        $this->assertArrayHasKey('TRY', $data['kpi']['bugun_ciro_pb']);
        $this->assertArrayHasKey('USD', $data['kpi']['bugun_ciro_pb']);
        $this->assertCount(2, $data['kpi']['bugun_ciro_pb']);
    }

    public function test_sorunlu_olay_ve_stok_ozetleri_uretilir(): void
    {
        $firma = $this->firma();
        $stok = $this->stokOlustur($firma, 'C1', 20, 2, 5);
        $stok->update(['negative_flag' => true, 'rezerve_miktar' => 5, 'stok_miktari' => 2]);

        SistemOlayi::query()->withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'tip' => 'odeme.callback.hata',
            'seviye' => 'error',
            'mesaj' => 'Hata',
            'context' => ['firma_id' => $firma->id],
        ]);

        $data = app(IsAnalitikServisi::class)->olustur($firma->id);
        $this->assertSame(1, (int) $data['operasyon']['negatif_stok']);
        $this->assertSame(1, (int) $data['operasyon']['rezerv_sorunlu']);
        $this->assertNotEmpty($data['listeler']['sorunlu_olaylar']);
    }
}
