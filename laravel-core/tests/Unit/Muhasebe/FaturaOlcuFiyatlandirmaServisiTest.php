<?php

namespace Tests\Unit\Muhasebe;

use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Servisler\FaturaOlcuFiyatlandirmaServisi;
use PHPUnit\Framework\TestCase;

class FaturaOlcuFiyatlandirmaServisiTest extends TestCase
{
    private FaturaOlcuFiyatlandirmaServisi $servis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servis = new FaturaOlcuFiyatlandirmaServisi();
    }

    public function test_ana_birim_fiyati_adet_fiyatina_donusur(): void
    {
        $this->assertSame('4000.00000000', $this->servis->adetFiyati('1000', '4'));
    }

    public function test_adet_fiyati_ana_birim_fiyatina_donusur(): void
    {
        $this->assertSame('1000.00000000', $this->servis->anaBirimFiyati('4000', '4'));
    }

    public function test_toplam_fiyat_miktarla_carpilir(): void
    {
        $this->assertSame('4000.00000000', $this->servis->toplam('1000', '4'));
    }

    public function test_ana_fiyat_ve_adet_fiyati_toplami_korur(): void
    {
        $ana = $this->servis->toplam('1000', '4');
        $adet = $this->servis->adetFiyati('1000', '4');
        $this->assertSame($ana, $this->servis->toplam($adet, '1'));
    }

    public function test_ayni_katsayili_coklu_olcu_donusur(): void
    {
        $sonuc = $this->servis->cokluOlcuDonusumu(['4', '4'], '1000', 'ana');
        $this->assertSame('4000.00000000', $sonuc['adet_fiyati']);
        $this->assertSame('ortak_katsayi', $sonuc['donusum_turu']);
    }

    public function test_farkli_katsayili_adet_fiyati_otomatik_reddedilir(): void
    {
        $this->expectException(IsKuraliIstisnasi::class);
        $this->servis->cokluOlcuDonusumu(['4', '2'], '1000', 'adet');
    }

    public function test_farkli_katsayili_ana_birim_fiyatina_izin_verilir(): void
    {
        $sonuc = $this->servis->cokluOlcuDonusumu(['4', '2'], '1000', 'ana');
        $this->assertSame('ana_birim', $sonuc['donusum_turu']);
        $this->assertSame('1000.00000000', $sonuc['ana_birim_fiyati']);
    }

    public function test_dogrudan_ortak_adet_fiyati_snapshotlanir(): void
    {
        $sonuc = $this->servis->cokluOlcuDonusumu(['4', '2'], '4000', 'adet', true);
        $this->assertSame('4000.00000000', $sonuc['adet_fiyati']);
        $this->assertSame('dogrudan_ortak_adet', $sonuc['donusum_turu']);
    }

    public function test_yuksek_hassasiyet_float_kullanmadan_korunur(): void
    {
        $this->assertSame('0.33333333', $this->servis->adetFiyati('1', '0.3333333333333333'));
    }

    public function test_sifir_katsayi_reddedilir(): void
    {
        $this->expectException(IsKuraliIstisnasi::class);
        $this->servis->anaBirimFiyati('100', '0');
    }

    public function test_negatif_fiyat_miktari_reddedilir(): void
    {
        $this->expectException(IsKuraliIstisnasi::class);
        $this->servis->toplam('100', '-1');
    }

    public function test_snapshot_katsayilari_sekiz_basamakla_doner(): void
    {
        $sonuc = $this->servis->cokluOlcuDonusumu(['4.123456789', '4.123456789'], '10', 'ana');
        $this->assertSame(['4.12345678', '4.12345678'], $sonuc['kat_sayilari']);
    }
}
