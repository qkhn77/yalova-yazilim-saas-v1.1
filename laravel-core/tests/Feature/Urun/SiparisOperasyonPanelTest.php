<?php

namespace Tests\Feature\Urun;

use App\Models\Ecommerce\Odeme;
use App\Models\Ecommerce\Siparis;
use App\Models\Ecommerce\SiparisGecmisi;
use App\Models\Firma;
use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokKategorisi;
use App\Modules\Urun\Servisler\SiparisDurumGecisServisi;
use App\Modules\Urun\Servisler\SiparisOdemeServisi;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokKartiTuru;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unpublished-web')]
class SiparisOperasyonPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\OnePagePublicSiteMiddleware::class);
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    private function firmaOlustur(): Firma
    {
        return Firma::query()->create([
            'ad' => 'Sipariş Op Firma',
            'kisa_ad' => 'SO',
            'firma_kodu' => 'SO-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $ek
     */
    private function siparisOlustur(string $durum, array $ek = []): Siparis
    {
        $firma = $this->firmaOlustur();

        return Siparis::query()->create(array_merge([
            'siparis_no' => 'SP-T-'.uniqid(),
            'firma_id' => $firma->id,
            'musteri_ad_soyad' => 'Test Müşteri',
            'musteri_email' => 'musteri@example.com',
            'musteri_telefon' => '05001234567',
            'teslimat_adresi' => 'Test adres',
            'notlar' => null,
            'para_birimi' => 'TRY',
            'ara_toplam' => 100,
            'kdv_toplam' => 0,
            'genel_toplam' => 100,
            'durum' => $durum,
            'stok_dusuldu_mi' => false,
        ], $ek));
    }

    public function test_durum_gecis_onaylandi_gonderildi(): void
    {
        $s = $this->siparisOlustur(Siparis::DURUM_ONAYLANDI_YENI);
        app(SiparisDurumGecisServisi::class)->durumuGuncelle($s, Siparis::DURUM_GONDERILDI);
        $this->assertSame(Siparis::DURUM_GONDERILDI, $s->fresh()->durum);
    }

    public function test_uygunsuz_durum_gecisi_engellenir(): void
    {
        $this->expectException(ValidationException::class);
        $s = $this->siparisOlustur(Siparis::DURUM_ONAYLANDI_YENI);
        app(SiparisDurumGecisServisi::class)->durumuGuncelle($s, Siparis::DURUM_TESLIM_EDILDI);
    }

    public function test_iptal_sonrasi_gonderildiye_gecis_engellenir(): void
    {
        $this->expectException(ValidationException::class);
        $s = $this->siparisOlustur(Siparis::DURUM_IPTAL_EDILDI);
        app(SiparisDurumGecisServisi::class)->durumuGuncelle($s, Siparis::DURUM_GONDERILDI);
    }

    public function test_teslim_sonrasi_durum_degistirilemez(): void
    {
        $this->expectException(ValidationException::class);
        $s = $this->siparisOlustur(Siparis::DURUM_TESLIM_EDILDI);
        app(SiparisDurumGecisServisi::class)->durumuGuncelle($s, Siparis::DURUM_GONDERILDI);
    }

    public function test_iptalde_stok_dusuldu_ise_stok_geri_gelir_ve_log_olusur(): void
    {
        $firma = $this->firmaOlustur();
        $kategori = StokKategorisi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'KTG',
            'ad' => 'Kategori',
            'aktif_mi' => true,
            'is_sabit' => false,
        ]);
        $birim = Birim::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'AD',
            'ad' => 'Adet',
            'aktif_mi' => true,
            'is_sabit' => false,
        ]);
        $stok = StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'STK-SOP',
            'ad' => 'Op Test',
            'slug' => 'op-test-'.uniqid(),
            'tur' => StokKartiTuru::ETicaret->value,
            'durum' => HesapDurumu::Aktif->value,
            'kategori_id' => $kategori->id,
            'kategori_kodu' => $kategori->kod,
            'birim' => $birim->kod,
            'satis_fiyati' => 50,
            'kdv_orani' => 0,
            'stok_takip' => true,
            'stok_miktari' => 10,
            'rezerve_miktar' => 0,
        ]);

        $siparis = Siparis::query()->create([
            'siparis_no' => 'SP-ST-'.uniqid(),
            'firma_id' => $firma->id,
            'musteri_ad_soyad' => 'X',
            'musteri_email' => 'x@x.com',
            'musteri_telefon' => '05',
            'teslimat_adresi' => 'A',
            'para_birimi' => 'TRY',
            'ara_toplam' => 50,
            'kdv_toplam' => 0,
            'genel_toplam' => 50,
            'durum' => Siparis::DURUM_ONAYLANDI_YENI,
            'stok_dusuldu_mi' => true,
        ]);

        $siparis->kalemler()->create([
            'stok_karti_id' => $stok->id,
            'urun_adi_snapshot' => $stok->ad,
            'urun_kodu_snapshot' => $stok->kod,
            'miktar' => 2,
            'stok_rezerv_miktari' => 0,
            'birim_fiyat' => 25,
            'kdv_orani' => 0,
            'satir_toplami' => 50,
        ]);

        StokKarti::tenantScopeOlmadan(function () use ($stok): void {
            StokKarti::query()->whereKey($stok->id)->update(['stok_miktari' => 8]);
        });

        app(SiparisOdemeServisi::class)->siparisIptalEt($siparis->fresh(), 'Müşteri vazgeçti');

        $this->assertSame(Siparis::DURUM_IPTAL_EDILDI, $siparis->fresh()->durum);
        $this->assertDatabaseHas('siparis_gecmisleri', [
            'siparis_id' => $siparis->id,
            'olay' => SiparisGecmisi::OLAY_IPTAL,
        ]);

        $stokTaze = StokKarti::tenantScopeOlmadan(fn () => StokKarti::query()->find($stok->id));
        $this->assertSame(10.0, (float) $stokTaze->stok_miktari);
    }

    public function test_odeme_durumu_filtresi_son_odeme_iliskisi(): void
    {
        $a = $this->siparisOlustur(Siparis::DURUM_ONAYLANDI_YENI);
        $b = $this->siparisOlustur(Siparis::DURUM_ONAY_BEKLIYOR);

        Odeme::query()->create([
            'siparis_id' => $a->id,
            'odeme_no' => 'ODM-A-'.uniqid(),
            'tutar' => 10,
            'para_birimi' => 'TRY',
            'durum' => Odeme::DURUM_BASARILI,
            'provider' => 'mock',
            'provider_ref' => 'ref-a',
        ]);

        Odeme::query()->create([
            'siparis_id' => $b->id,
            'odeme_no' => 'ODM-B-'.uniqid(),
            'tutar' => 10,
            'para_birimi' => 'TRY',
            'durum' => Odeme::DURUM_BEKLEMEDE,
            'provider' => 'mock',
            'provider_ref' => 'ref-b',
        ]);

        $ids = Siparis::query()
            ->whereHas('sonOdeme', fn ($q) => $q->where('durum', Odeme::DURUM_BASARILI))
            ->pluck('id')
            ->all();

        $this->assertContains($a->id, $ids);
        $this->assertNotContains($b->id, $ids);
    }

    public function test_siparis_detay_iliskileri_yuklenir(): void
    {
        $s = $this->siparisOlustur(Siparis::DURUM_ONAYLANDI_YENI);
        Odeme::query()->create([
            'siparis_id' => $s->id,
            'odeme_no' => 'ODM-D-'.uniqid(),
            'tutar' => 10,
            'para_birimi' => 'TRY',
            'durum' => Odeme::DURUM_BASARILI,
            'provider' => 'mock',
            'provider_ref' => 'x',
        ]);

        $yuklu = Siparis::query()
            ->with(['kalemler', 'odemeler', 'gecmisleri.kullanici', 'sonOdeme'])
            ->findOrFail($s->id);

        $this->assertTrue($yuklu->relationLoaded('kalemler'));
        $this->assertTrue($yuklu->relationLoaded('odemeler'));
        $this->assertTrue($yuklu->relationLoaded('gecmisleri'));
        $this->assertNotNull($yuklu->sonOdeme);
    }

    public function test_kargo_alanlari_veritabanina_yazilir(): void
    {
        $s = $this->siparisOlustur(Siparis::DURUM_GONDERILDI);
        $s->update([
            'kargo_firmasi' => 'Test Kargo',
            'takip_no' => 'TRK123',
            'kargo_tarihi' => now()->toDateString(),
        ]);

        $this->assertDatabaseHas('siparisler', [
            'id' => $s->id,
            'kargo_firmasi' => 'Test Kargo',
            'takip_no' => 'TRK123',
        ]);
    }

    public function test_durum_degistiginde_gecmis_kaydi_olusur(): void
    {
        $s = $this->siparisOlustur(Siparis::DURUM_ONAYLANDI_YENI);
        app(SiparisDurumGecisServisi::class)->durumuGuncelle($s, Siparis::DURUM_GONDERILDI);

        $this->assertDatabaseHas('siparis_gecmisleri', [
            'siparis_id' => $s->id,
            'olay' => SiparisGecmisi::OLAY_DURUM_DEGISTI,
        ]);
    }

    public function test_siparis_takip_placeholder_sayfasi(): void
    {
        $this->get(route('orders.track'))
            ->assertOk()
            ->assertSee('Sipariş durumunuzu sorgulayın', false);
    }

    public function test_tamamlanmis_siparis_cannot_be_cancelled_via_odeme_servisi(): void
    {
        $this->expectException(ValidationException::class);
        $s = $this->siparisOlustur(Siparis::DURUM_TESLIM_EDILDI, ['stok_dusuldu_mi' => true]);
        app(SiparisOdemeServisi::class)->siparisIptalEt($s);
    }
}
