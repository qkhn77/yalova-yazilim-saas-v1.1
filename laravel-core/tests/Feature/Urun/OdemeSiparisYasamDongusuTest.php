<?php

namespace Tests\Feature\Urun;

use App\Models\Ecommerce\Odeme;
use App\Models\Ecommerce\Siparis;
use App\Models\Ecommerce\SiparisGecmisi;
use App\Models\Firma;
use App\Models\FirmaModulu;
use App\Models\Modul;
use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\StokDepoBakiyesi;
use App\Models\Muhasebe\StokKategorisi;
use App\Modules\Urun\Servisler\SiparisOdemeServisi;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Services\EcommerceFirmaAyarServisi;
use App\Services\FirmaAyarDeposu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Feature\Urun\Concerns\CheckoutTestVerileri;

class OdemeSiparisYasamDongusuTest extends TestCase
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

    private function firmaOlustur(): Firma
    {
        $firma = Firma::query()->create([
            'ad' => 'Ödeme Firma',
            'kisa_ad' => 'OF',
            'firma_kodu' => 'OF-'.uniqid(),
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

    private function urunHazirla(float $stok = 10): StokKarti
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

        return StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'STK-OD',
            'ad' => 'Ödeme Test Ürün',
            'slug' => 'odeme-test-urun',
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
            'ad' => 'E-ticaret',
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

    public function test_odeme_basarili_siparis_odendi_ve_stok_duser(): void
    {
        $urun = $this->urunHazirla(10);
        $this->post(route('cart.add', $urun->slug), ['miktar' => 2]);
        $this->post(route('checkout.store'), $this->checkoutVerisi());

        $siparis = Siparis::query()->firstOrFail();
        $this->assertSame(Siparis::DURUM_ONAY_BEKLIYOR, $siparis->durum);

        $kalem = $siparis->kalemler()->firstOrFail();
        $this->assertSame('2.00000000', (string) $kalem->stok_rezerv_miktari);

        $urunTaze = StokKarti::tenantScopeOlmadan(fn () => StokKarti::query()->find($urun->id));
        $this->assertSame(2.0, (float) $urunTaze->rezerve_miktar);
        $this->assertSame(10.0, (float) $urunTaze->stok_miktari);

        $this->post(route('odeme.basarili', $siparis))
            ->assertRedirect(route('checkout.success'));

        $siparis->refresh();
        $this->assertSame(Siparis::DURUM_ONAYLANDI_YENI, $siparis->durum);
        $this->assertTrue($siparis->stok_dusuldu_mi);

        $this->assertDatabaseHas('odemeler', [
            'siparis_id' => $siparis->id,
            'durum' => Odeme::DURUM_BASARILI,
        ]);

        $urunTaze = StokKarti::tenantScopeOlmadan(fn () => StokKarti::query()->find($urun->id));
        $this->assertSame(8.0, (float) $urunTaze->stok_miktari);
        $this->assertSame(0.0, (float) $urunTaze->rezerve_miktar);
    }

    public function test_depo_modulu_acikken_e_ticaret_rezervi_ve_stok_hareketi_depo_bakiyesine_yansir(): void
    {
        $urun = $this->urunHazirla(10);
        $depo = Depo::query()->create([
            'firma_id' => $urun->firma_id,
            'kod' => 'MERKEZ-'.uniqid(),
            'ad' => 'Merkez Depo',
            'varsayilan_mi' => true,
            'aktif_mi' => true,
        ]);
        app(FirmaAyarDeposu::class)->yaz($urun->firma_id, 'stok_depo_modulu_aktif_mi', true);
        app(FirmaAyarDeposu::class)->yaz($urun->firma_id, 'stok_varsayilan_depo_id', $depo->id);

        $this->post(route('cart.add', $urun->slug), ['miktar' => 2]);
        $this->post(route('checkout.store'), $this->checkoutVerisi());

        $siparis = Siparis::query()->firstOrFail();
        $kalem = $siparis->kalemler()->firstOrFail();
        $this->assertSame($depo->id, (int) $kalem->depo_id);
        $this->assertDatabaseHas('stok_depo_bakiyeleri', [
            'firma_id' => $urun->firma_id,
            'depo_id' => $depo->id,
            'stok_id' => $urun->id,
            'miktar' => '10.0000',
            'rezerve_miktar' => '2.0000',
        ]);

        $this->post(route('odeme.basarili', $siparis))
            ->assertRedirect(route('checkout.success'));

        $this->assertSame('8.00000000', (string) StokDepoBakiyesi::tenantScopeOlmadan(fn () => StokDepoBakiyesi::query()
            ->where('depo_id', $depo->id)
            ->where('stok_id', $urun->id)
            ->value('miktar')));
        $this->assertSame('0.00000000', (string) StokDepoBakiyesi::tenantScopeOlmadan(fn () => StokDepoBakiyesi::query()
            ->where('depo_id', $depo->id)
            ->where('stok_id', $urun->id)
            ->value('rezerve_miktar')));
    }

    public function test_odeme_basarisiz_siparis_acik_kalir_ve_tekrar_denenebilir(): void
    {
        $urun = $this->urunHazirla(10);
        $this->post(route('cart.add', $urun->slug), ['miktar' => 2]);
        $this->post(route('checkout.store'), $this->checkoutVerisi());

        $siparis = Siparis::query()->firstOrFail();

        $this->post(route('odeme.basarisiz', $siparis))
            ->assertRedirect(route('odeme.show', $siparis));

        $siparis->refresh();
        $this->assertSame(Siparis::DURUM_BASARISIZ_ODEME, $siparis->durum);
        $this->assertSame(1, (int) $siparis->odeme_deneme_sayisi);

        $this->assertDatabaseHas('siparis_gecmisleri', [
            'siparis_id' => $siparis->id,
            'olay' => SiparisGecmisi::OLAY_ODEME_BASARISIZ,
        ]);

        $this->assertDatabaseHas('odemeler', [
            'siparis_id' => $siparis->id,
            'durum' => Odeme::DURUM_BASARISIZ,
        ]);

        $urunTaze = StokKarti::tenantScopeOlmadan(fn () => StokKarti::query()->find($urun->id));
        $this->assertSame(10.0, (float) $urunTaze->stok_miktari);
        $this->assertSame(2.0, (float) $urunTaze->rezerve_miktar);

        $this->post(route('odeme.tekrar_dene', $siparis))
            ->assertRedirect(route('odeme.show', $siparis));

        $this->assertDatabaseHas('siparis_gecmisleri', [
            'siparis_id' => $siparis->id,
            'olay' => SiparisGecmisi::OLAY_ODEME_TEKRAR_DENENDI,
        ]);

        $this->assertDatabaseHas('odemeler', [
            'siparis_id' => $siparis->id,
            'durum' => Odeme::DURUM_BEKLEMEDE,
        ]);

        $this->post(route('odeme.basarili', $siparis->fresh()))
            ->assertRedirect(route('checkout.success'));

        $siparis->refresh();
        $this->assertSame(Siparis::DURUM_ONAYLANDI_YENI, $siparis->durum);
    }

    public function test_iptalde_finans_iade_otomatik_ters_kayit_olusturulur(): void
    {
        config([
            'ecommerce.finans_iade_otomatik' => true,
        ]);

        $urun = $this->urunHazirla(10);
        $firma = Firma::query()->findOrFail((int) $urun->firma_id);
        [$cari, $kasa] = $this->cariVeKasaOlustur($firma);

        config([
            'ecommerce.tahsilat_cari_id' => $cari->id,
            'ecommerce.tahsilat_kasa_id' => $kasa->id,
        ]);

        $this->post(route('cart.add', $urun->slug), ['miktar' => 1]);
        $this->post(route('checkout.store'), $this->checkoutVerisi());

        $siparis = Siparis::query()->firstOrFail();

        $yanit = $this->post(route('odeme.basarili', $siparis));
        $yanit->assertRedirect(route('checkout.success'));

        $siparis->refresh();
        $this->assertSame(Siparis::DURUM_ONAYLANDI_YENI, $siparis->durum);

        $orijinalFinans = FinansHareketi::query()->withoutGlobalScopes()
            ->where('firma_id', $firma->id)
            ->where('referans_turu', Siparis::REFERANS_TURU_FINANS)
            ->where('referans_id', $siparis->id)
            ->where('durum', 'aktif')
            ->orderByDesc('id')
            ->firstOrFail();

        app(SiparisOdemeServisi::class)->siparisIptalEt($siparis->fresh(), 'İptal isteği');

        $this->assertDatabaseHas('siparis_gecmisleri', [
            'siparis_id' => $siparis->id,
            'olay' => SiparisGecmisi::OLAY_IPTAL,
        ]);

        $this->assertDatabaseHas('finans_hareketleri', [
            'firma_id' => $firma->id,
            'iptal_edilen_hareket_id' => $orijinalFinans->id,
            'referans_turu' => Siparis::REFERANS_TURU_FINANS,
            'referans_id' => $siparis->id,
        ]);

        $tersSayisiIlk = FinansHareketi::query()->withoutGlobalScopes()
            ->where('firma_id', $firma->id)
            ->where('referans_turu', Siparis::REFERANS_TURU_FINANS)
            ->where('referans_id', $siparis->id)
            ->where('tur', 'mahsup')
            ->where('durum', 'aktif')
            ->count();

        // Aynı sipariş ikinci kez iptal edilince ikinci finans iadesi oluşmamalı.
        app(SiparisOdemeServisi::class)->siparisIptalEt($siparis->fresh(), 'İptal isteği (2)');

        $tersSayisiIkinci = FinansHareketi::query()->withoutGlobalScopes()
            ->where('firma_id', $firma->id)
            ->where('referans_turu', Siparis::REFERANS_TURU_FINANS)
            ->where('referans_id', $siparis->id)
            ->where('tur', 'mahsup')
            ->where('durum', 'aktif')
            ->count();

        $this->assertSame($tersSayisiIlk, $tersSayisiIkinci);
    }

    public function test_odeme_sonrasi_iptalde_stok_geri_gelir(): void
    {
        $urun = $this->urunHazirla(10);
        $this->post(route('cart.add', $urun->slug), ['miktar' => 2]);
        $this->post(route('checkout.store'), $this->checkoutVerisi());

        $siparis = Siparis::query()->firstOrFail();
        $this->post(route('odeme.basarili', $siparis));

        $siparis->refresh();
        $this->assertSame(8.0, (float) StokKarti::tenantScopeOlmadan(fn () => StokKarti::query()->find($urun->id))->stok_miktari);

        app(SiparisOdemeServisi::class)->siparisIptalEt($siparis->fresh());

        $siparis->refresh();
        $this->assertSame(Siparis::DURUM_IPTAL_EDILDI, $siparis->durum);
        $this->assertFalse($siparis->stok_dusuldu_mi);

        $urunTaze = StokKarti::tenantScopeOlmadan(fn () => StokKarti::query()->find($urun->id));
        $this->assertSame(10.0, (float) $urunTaze->stok_miktari);
    }

    public function test_odeme_zaman_asimi_sonrasi_basarisiz_odeme_olur(): void
    {
        $this->freezeTime();
        $urun = $this->urunHazirla(10);
        $this->post(route('cart.add', $urun->slug), ['miktar' => 2]);
        $this->post(route('checkout.store'), $this->checkoutVerisi());

        $siparis = Siparis::query()->firstOrFail();
        $this->assertNotNull($siparis->odeme_suresi_bitis_at);

        $this->travel(16)->minutes();
        $this->artisan('siparis:odeme-zaman-asimi-isle');

        $siparis->refresh();
        $this->assertSame(Siparis::DURUM_BASARISIZ_ODEME, $siparis->durum);

        $urunTaze = StokKarti::tenantScopeOlmadan(fn () => StokKarti::query()->find($urun->id));
        $this->assertSame(0.0, (float) $urunTaze->rezerve_miktar);
        $this->assertSame(10.0, (float) $urunTaze->stok_miktari);
    }

    public function test_odeme_basarili_finans_kaydi_olusur(): void
    {
        $urun = $this->urunHazirla(10);
        $firma = Firma::query()->findOrFail((int) $urun->firma_id);
        [$cari, $kasa] = $this->cariVeKasaOlustur($firma);

        config([
            'ecommerce.tahsilat_cari_id' => $cari->id,
            'ecommerce.tahsilat_kasa_id' => $kasa->id,
        ]);

        $this->post(route('cart.add', $urun->slug), ['miktar' => 1]);
        $this->post(route('checkout.store'), $this->checkoutVerisi());

        $siparis = Siparis::query()->firstOrFail();
        $yanit = $this->post(route('odeme.basarili', $siparis));
        $yanit->assertRedirect(route('checkout.success'));
        $siparis->refresh();
        $this->assertSame(Siparis::DURUM_ONAYLANDI_YENI, $siparis->durum);

        $this->assertDatabaseHas('finans_hareketleri', [
            'firma_id' => $firma->id,
            'referans_turu' => Siparis::REFERANS_TURU_FINANS,
            'referans_id' => $siparis->id,
        ]);
    }

    public function test_cift_odeme_idempotent(): void
    {
        $urun = $this->urunHazirla(10);
        $this->post(route('cart.add', $urun->slug), ['miktar' => 1]);
        $this->post(route('checkout.store'), $this->checkoutVerisi());

        $siparis = Siparis::query()->firstOrFail();
        $firma = Firma::query()->findOrFail((int) $urun->firma_id);
        [$cari, $kasa] = $this->cariVeKasaOlustur($firma);

        config([
            'ecommerce.tahsilat_cari_id' => $cari->id,
            'ecommerce.tahsilat_kasa_id' => $kasa->id,
        ]);

        $this->post(route('odeme.basarili', $siparis));
        $this->post(route('odeme.basarili', $siparis->fresh()));

        $siparis->refresh();
        $this->assertSame(Siparis::DURUM_ONAYLANDI_YENI, $siparis->durum);

        $adet = FinansHareketi::query()->withoutGlobalScopes()
            ->where('firma_id', $firma->id)
            ->where('referans_turu', Siparis::REFERANS_TURU_FINANS)
            ->where('referans_id', $siparis->id)
            ->count();

        $this->assertSame(1, $adet);
    }

    public function test_odeme_webhook_placeholder_csrf_muaf(): void
    {
        $urun = $this->urunHazirla(10);
        $this->post(route('cart.add', $urun->slug), ['miktar' => 1]);
        $this->post(route('checkout.store'), $this->checkoutVerisi());

        $siparis = Siparis::query()->firstOrFail();

        // Beklenen: CSRF engeli olmamalı (419 değil); callback güvenlik katmanı 4xx dönmeli.
        $this->post(route('odeme.webhook.callback', ['provider' => 'paytr']), [
            'hash' => 'BAD_HASH',
            'callback_id' => 'CB_1',
            'merchant_oid' => $siparis->id.'-test-ref',
            'status' => 'success',
            'total_amount' => (string) ((int) round(((float) $siparis->genel_toplam) * 100)),
        ])->assertStatus(409);
    }
}
