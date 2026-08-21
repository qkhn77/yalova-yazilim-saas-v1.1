<?php

namespace Tests\Feature\Urun;

use App\Models\Ecommerce\Siparis;
use App\Models\Firma;
use App\Models\FirmaModulu;
use App\Models\Modul;
use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\KasaHareketi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokKategorisi;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Services\EcommerceFirmaAyarServisi;
use App\Services\EcommerceOdemeZamanAsimiFallbackServisi;
use App\Services\FirmaAyarDeposu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Feature\Urun\Concerns\CheckoutTestVerileri;

#[\PHPUnit\Framework\Attributes\Group('unpublished-web')]
class EcommerceTahsilatAyarlarFallbackTest extends TestCase
{
    use CheckoutTestVerileri;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
        config(['ecommerce.odeme_dakika' => 15]);
    }

    private function firmaOlustur(string $ad = 'E-ticaret Firma'): Firma
    {
        $firma = Firma::query()->create([
            'ad' => $ad,
            'kisa_ad' => substr($ad, 0, 2).'-'.uniqid(),
            'firma_kodu' => 'F-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);

        $this->ecommerceModulAktifEt($firma);

        return $firma;
    }

    private function ecommerceModulAktifEt(Firma $firma): void
    {
        $modul = Modul::query()->firstOrCreate(
            ['kod' => 'e_ticaret'],
            ['ad' => 'E-ticaret', 'aktif_mi' => true, 'siralama' => 50],
        );
        if (! $modul->aktif_mi) {
            $modul->update(['aktif_mi' => true]);
        }

        FirmaModulu::query()->updateOrCreate(
            ['firma_id' => $firma->id, 'modul_id' => $modul->id],
            ['durum' => 'aktif'],
        );

        $cari = Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'EC-C-'.uniqid(),
            'ad' => 'E-ticaret Cari',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);
        $kasa = KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'EC-K-'.uniqid(),
            'ad' => 'E-ticaret Kasa',
            'para_birimi' => 'TRY',
            'durum' => HesapDurumu::Aktif->value,
        ]);

        app(EcommerceFirmaAyarServisi::class)->kaydetAyarlar((int) $firma->id, [
            'ecommerce_etkin_mi' => true,
            'ecommerce_tahsilat_cari_id' => $cari->id,
            'ecommerce_tahsilat_kasa_id' => $kasa->id,
            'ecommerce_otomatik_genel_kasa_kullan' => false,
            'ecommerce_cron_fallback_etkin_mi' => true,
        ]);

        $this->checkoutTestVarsayilanlariniOlustur($firma);
    }

    private function urunHazirlaFirma(Firma $firma, float $stok = 10): StokKarti
    {
        $kategori = StokKategorisi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'KTG-'.uniqid(),
            'ad' => 'Kategori',
            'aktif_mi' => true,
            'is_sabit' => false,
        ]);
        $birim = Birim::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'AD-'.uniqid(),
            'ad' => 'Adet',
            'aktif_mi' => true,
            'is_sabit' => false,
        ]);

        return StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'STK-EC-'.uniqid(),
            'ad' => 'E-ticaret Test Ürün',
            'slug' => 'ec-test-'.uniqid(),
            'tur' => StokKartiTuru::ETicaret->value,
            'durum' => HesapDurumu::Aktif->value,
            'kategori_id' => $kategori->id,
            'kategori_kodu' => $kategori->kod,
            'birim' => $birim->kod,
            'satis_fiyati' => 50,
            'kdv_orani' => 0,
            'stok_takip' => true,
            'stok_miktari' => $stok,
            'rezerve_miktar' => 0,
        ]);
    }

    private function checkoutVerisi(): array
    {
        return $this->checkoutTestVerisi();
    }

    private function cariVeKasaOlustur(Firma $firma): array
    {
        $cari = Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'C-'.uniqid(),
            'ad' => 'E-ticaret Cari',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        $kasa = KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'K-'.uniqid(),
            'ad' => 'Web kasa',
            'para_birimi' => 'TRY',
            'durum' => HesapDurumu::Aktif->value,
        ]);

        return [$cari, $kasa];
    }

    public function test_firma_bazli_ecommerce_tahsilat_ayar_kaydedilir(): void
    {
        $firma = $this->firmaOlustur('F1');
        $ayarServisi = app(EcommerceFirmaAyarServisi::class);
        $depo = app(FirmaAyarDeposu::class);

        [$cari, $kasa] = $this->cariVeKasaOlustur($firma);

        $ayarServisi->kaydetAyarlar($firma->id, [
            'ecommerce_etkin_mi' => true,
            'ecommerce_tahsilat_cari_id' => $cari->id,
            'ecommerce_tahsilat_kasa_id' => $kasa->id,
            'ecommerce_odeme_dakika' => 20,
            'ecommerce_otomatik_genel_kasa_kullan' => true,
            'ecommerce_cron_fallback_etkin_mi' => true,
        ]);

        $this->assertSame(true, (bool) $depo->oku($firma->id, 'ecommerce_etkin_mi', false));
        $this->assertSame($cari->id, (int) $depo->oku($firma->id, 'ecommerce_tahsilat_cari_id', 0));
        $this->assertSame($kasa->id, (int) $depo->oku($firma->id, 'ecommerce_tahsilat_kasa_id', 0));
        $this->assertSame(20, (int) $depo->oku($firma->id, 'ecommerce_odeme_dakika', 0));
    }

    public function test_firma_ozel_cari_kasa_listeleri_dogru_firmayi_filter_eder(): void
    {
        $f1 = $this->firmaOlustur('F1');
        $f2 = $this->firmaOlustur('F2');
        $ayarServisi = app(EcommerceFirmaAyarServisi::class);

        [$cari1, $kasa1] = $this->cariVeKasaOlustur($f1);
        [$cari2, $kasa2] = $this->cariVeKasaOlustur($f2);

        $cariSec = $ayarServisi->cariSecenekleri($f1->id);
        $kasaSec = $ayarServisi->kasaSecenekleri($f1->id);

        $this->assertArrayHasKey((string) $cari1->id, $cariSec);
        $this->assertArrayNotHasKey((string) $cari2->id, $cariSec);

        $this->assertArrayHasKey((int) $kasa1->id, $kasaSec);
        $this->assertArrayNotHasKey((int) $kasa2->id, $kasaSec);
    }

    public function test_eksik_ayar_kullanici_dostu_hata_olusturur(): void
    {
        $firma = $this->firmaOlustur('Eksik Ayar');
        $ayarServisi = app(EcommerceFirmaAyarServisi::class);

        $ayarServisi->kaydetAyarlar($firma->id, [
            'ecommerce_etkin_mi' => true,
            'ecommerce_tahsilat_cari_id' => null,
            'ecommerce_tahsilat_kasa_id' => null,
        ]);

        try {
            $ayarServisi->kontrolTahsilatBaslangicVeyaHata($firma->id);
            $this->fail('Beklenen ValidationException atılmadı.');
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            $this->assertStringContainsString('Tahsilat cari hesabı seçilmemiş', (string) $msg);
            $this->assertStringContainsString('firma-ayarlari', (string) $msg);
        }
    }

    public function test_kasa_yoksa_otomatik_genel_kasa_olusturulur_ve_baglanir(): void
    {
        $firma = $this->firmaOlustur('Otomatik Kasa');
        $ayarServisi = app(EcommerceFirmaAyarServisi::class);
        $depo = app(FirmaAyarDeposu::class);

        KasaHesabi::query()->withTrashed()->forceDelete();
        $this->assertDatabaseCount('kasa_hesaplari', 0);

        $ayarServisi->kaydetAyarlar($firma->id, [
            'ecommerce_etkin_mi' => true,
            'ecommerce_tahsilat_cari_id' => null,
            'ecommerce_tahsilat_kasa_id' => null,
            'ecommerce_otomatik_genel_kasa_kullan' => true,
        ]);

        $kasaId = $depo->oku($firma->id, 'ecommerce_tahsilat_kasa_id', null);
        $this->assertNotNull($kasaId);

        $kasa = KasaHesabi::query()->findOrFail((int) $kasaId);
        $this->assertSame('Genel Kasa', $kasa->ad);
    }

    public function test_fallback_zaman_asimi_throttle_mechanizmasi_works(): void
    {
        $firma = $this->firmaOlustur('Fallback');
        $depo = app(FirmaAyarDeposu::class);
        $fallback = app(EcommerceOdemeZamanAsimiFallbackServisi::class);

        $siparis = Siparis::query()->create([
            'siparis_no' => 'SP-'.uniqid(),
            'firma_id' => $firma->id,
            'musteri_ad_soyad' => 'X',
            'musteri_email' => 'x@x.com',
            'musteri_telefon' => '05',
            'teslimat_adresi' => 'A',
            'notlar' => null,
            'para_birimi' => 'TRY',
            'ara_toplam' => 50,
            'kdv_toplam' => 0,
            'genel_toplam' => 50,
            'durum' => Siparis::DURUM_ONAY_BEKLIYOR,
            'stok_dusuldu_mi' => false,
            'odeme_suresi_bitis_at' => now()->subMinutes(10),
            'odeme_deneme_sayisi' => 0,
        ]);

        $depo->yaz($firma->id, 'ecommerce_cron_fallback_etkin_mi', true);
        $depo->yaz($firma->id, 'ecommerce_son_zaman_asimi_isleme_at', now()->toDateTimeString());

        $fallback->tetikle($firma->id);
        $this->assertSame(Siparis::DURUM_ONAY_BEKLIYOR, $siparis->fresh()->durum);

        $depo->yaz($firma->id, 'ecommerce_son_zaman_asimi_isleme_at', now()->subMinutes(10)->toDateTimeString());
        $fallback->tetikle($firma->id);
        $this->assertSame(Siparis::DURUM_BASARISIZ_ODEME, $siparis->fresh()->durum);
    }

    public function test_cron_fallback_endpoint_token_korumali(): void
    {
        config(['ecommerce.cron_fallback_token' => 'token123']);

        $this->getJson('/sistem/cron/odeme-zaman-asimi')->assertStatus(403);
        $this->getJson('/sistem/cron/odeme-zaman-asimi?token=wrong')->assertStatus(403);

        $resp = $this->getJson('/sistem/cron/odeme-zaman-asimi?token=token123');
        $resp->assertOk();
        $resp->assertJsonPath('ok', true);
    }

    public function test_odeme_finans_kaydi_firma_ayarlarindan_cari_kasa_kullanir(): void
    {
        $firma = $this->firmaOlustur('Finans Firma');
        $urun = $this->urunHazirlaFirma($firma, 10);

        $ayarServisi = app(EcommerceFirmaAyarServisi::class);

        [$cariDogru, $kasaDogru] = $this->cariVeKasaOlustur($firma);
        [$cariLegacy, $kasaLegacy] = $this->cariVeKasaOlustur($firma);

        // Legacy config kasıtlı olarak farklı.
        config([
            'ecommerce.tahsilat_cari_id' => $cariLegacy->id,
            'ecommerce.tahsilat_kasa_id' => $kasaLegacy->id,
        ]);

        $ayarServisi->kaydetAyarlar($firma->id, [
            'ecommerce_etkin_mi' => true,
            'ecommerce_tahsilat_cari_id' => $cariDogru->id,
            'ecommerce_tahsilat_kasa_id' => $kasaDogru->id,
            'ecommerce_odeme_dakika' => 15,
        ]);

        $this->post(route('cart.add', $urun->slug), ['miktar' => 1]);
        $this->post(route('checkout.store'), $this->checkoutVerisi());

        $siparis = Siparis::query()->firstOrFail();
        $this->post(route('odeme.basarili', $siparis))->assertRedirect(route('checkout.success'));

        $finans = FinansHareketi::query()->withoutGlobalScopes()
            ->where('firma_id', $firma->id)
            ->where('referans_turu', Siparis::REFERANS_TURU_FINANS)
            ->where('referans_id', $siparis->id)
            ->firstOrFail();

        $siparis->refresh();
        $this->assertSame((int) $siparis->muhasebe_cari_id, (int) $finans->cari_id);
        $this->assertNotSame($cariLegacy->id, (int) $finans->cari_id);

        $kasaHareket = KasaHareketi::query()->where('finans_hareket_id', $finans->id)->firstOrFail();
        $this->assertSame($kasaDogru->id, (int) $kasaHareket->kasa_hesap_id);
    }

    public function test_firma_ayar_varsa_legacy_config_yok_sayilir(): void
    {
        $firma = $this->firmaOlustur('Legacy Firma');
        $urun = $this->urunHazirlaFirma($firma, 10);

        [$cari, $kasa] = $this->cariVeKasaOlustur($firma);

        // Firma özel ayarı yok; sadece legacy config üzerinden finans yazımı gelsin.
        config([
            'ecommerce.tahsilat_cari_id' => $cari->id,
            'ecommerce.tahsilat_kasa_id' => $kasa->id,
        ]);

        $this->post(route('cart.add', $urun->slug), ['miktar' => 1]);
        $this->post(route('checkout.store'), $this->checkoutVerisi());

        $siparis = Siparis::query()->firstOrFail();
        $this->post(route('odeme.basarili', $siparis))->assertRedirect(route('checkout.success'));

        $finans = FinansHareketi::query()->withoutGlobalScopes()
            ->where('firma_id', $firma->id)
            ->where('referans_turu', Siparis::REFERANS_TURU_FINANS)
            ->where('referans_id', $siparis->id)
            ->firstOrFail();

        $depo = app(FirmaAyarDeposu::class);
        $firmaKasaId = (int) $depo->oku($firma->id, 'ecommerce_tahsilat_kasa_id', 0);

        $siparis->refresh();
        $this->assertSame((int) $siparis->muhasebe_cari_id, (int) $finans->cari_id);
        $this->assertNotSame($cari->id, (int) $finans->cari_id);

        $kasaHareket = KasaHareketi::query()->where('finans_hareket_id', $finans->id)->firstOrFail();
        $this->assertSame($firmaKasaId, (int) $kasaHareket->kasa_hesap_id);
        $this->assertNotSame($kasa->id, (int) $kasaHareket->kasa_hesap_id);
    }
}
