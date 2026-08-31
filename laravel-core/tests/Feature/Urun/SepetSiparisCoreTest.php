<?php

namespace Tests\Feature\Urun;

use App\Models\Ecommerce\SepetKalemi;
use App\Models\Ecommerce\Siparis;
use App\Models\Ecommerce\SiparisKalemi;
use App\Models\Firma;
use App\Models\FirmaModulu;
use App\Models\Modul;
use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokKategorisi;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Services\EcommerceFirmaAyarServisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Feature\Urun\Concerns\CheckoutTestVerileri;

#[\PHPUnit\Framework\Attributes\Group('unpublished-web')]
class SepetSiparisCoreTest extends TestCase
{
    use CheckoutTestVerileri;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\EcommerceFrontErisimMiddleware::class);
        // Public cart rendering is tested independently of the temporary
        // unpublished-site gate; checkout/auth behavior remains covered by
        // dedicated route and middleware tests.
        $this->withoutMiddleware(\App\Http\Middleware\OnePagePublicSiteMiddleware::class);
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    private function firmaOlustur(): Firma
    {
        $firma = Firma::query()->create([
            'ad' => 'Sepet Firma',
            'kisa_ad' => 'SF',
            'firma_kodu' => 'SF-'.uniqid(),
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
            'kod' => 'STK-100',
            'ad' => 'Test Ürün',
            'slug' => 'test-urun',
            'tur' => StokKartiTuru::ETicaret->value,
            'durum' => HesapDurumu::Aktif->value,
            'kategori_id' => $kategori->id,
            'kategori_kodu' => $kategori->kod,
            'birim' => $birim->kod,
            'satis_fiyati' => 100,
            'kdv_orani' => 20,
            'stok_takip' => true,
            'stok_miktari' => $stok,
        ]);
    }

    public function test_urun_sepete_eklenir(): void
    {
        $urun = $this->urunHazirla();
        $this->post(route('cart.add', $urun->slug), ['miktar' => 1])
            ->assertRedirect('/');

        $this->assertDatabaseCount('sepetler', 1);
        $this->assertDatabaseHas('sepet_kalemleri', [
            'stok_karti_id' => $urun->id,
        ]);
    }

    public function test_adet_guncellenir(): void
    {
        $urun = $this->urunHazirla();
        $this->post(route('cart.add', $urun->slug), ['miktar' => 1]);
        $kalem = SepetKalemi::query()->firstOrFail();

        $this->patch(route('cart.update', $kalem->id), ['miktar' => 3])
            ->assertRedirect(route('cart.index'));

        $this->assertDatabaseHas('sepet_kalemleri', [
            'id' => $kalem->id,
            'miktar' => '3.0000',
        ]);
    }

    public function test_sepetten_silinir(): void
    {
        $urun = $this->urunHazirla();
        $this->post(route('cart.add', $urun->slug), ['miktar' => 1]);
        $kalem = SepetKalemi::query()->firstOrFail();

        $this->delete(route('cart.remove', $kalem->id))
            ->assertRedirect(route('cart.index'));

        $this->assertDatabaseMissing('sepet_kalemleri', ['id' => $kalem->id]);
    }

    public function test_stok_yetersizse_engellenir(): void
    {
        $urun = $this->urunHazirla(1);
        $this->post(route('cart.add', $urun->slug), ['miktar' => 2])
            ->assertSessionHasErrors('stok');
    }

    public function test_checkout_ile_siparis_olusur(): void
    {
        $urun = $this->urunHazirla(10);
        $this->post(route('cart.add', $urun->slug), ['miktar' => 2]);

        $this->post(route('checkout.store'), [
            ...$this->checkoutTestVerisi($urun->firma_id, [
                'musteri_ad_soyad' => 'Ali Veli',
                'musteri_email' => 'ali@example.com',
            ]),
            'notlar' => 'Kapıya bırak',
        ])->assertRedirect(route('odeme.show', Siparis::query()->firstOrFail()));

        $this->assertDatabaseCount('siparisler', 1);
        $siparis = Siparis::query()->firstOrFail();
        $this->assertSame(Siparis::DURUM_ONAY_BEKLIYOR, $siparis->durum);

        $urunTaze = StokKarti::tenantScopeOlmadan(fn () => StokKarti::query()->find($urun->id));
        $this->assertSame(10.0, (float) $urunTaze->stok_miktari);
    }

    public function test_siparis_kalemleri_snapshot_ile_olusur(): void
    {
        $urun = $this->urunHazirla(10);
        $this->post(route('cart.add', $urun->slug), ['miktar' => 1]);

        $this->post(route('checkout.store'), [
            ...$this->checkoutTestVerisi($urun->firma_id, [
                'musteri_ad_soyad' => 'Ali Veli',
                'musteri_email' => 'ali@example.com',
            ]),
        ]);

        $kalem = SiparisKalemi::query()->firstOrFail();
        $this->assertSame('Test Ürün', $kalem->urun_adi_snapshot);
        $this->assertSame('STK-100', $kalem->urun_kodu_snapshot);
        $this->assertSame(100.0, (float) $kalem->birim_fiyat);
    }

    public function test_odeme_basarili_sonrasi_sepet_bosalir(): void
    {
        $urun = $this->urunHazirla(10);
        $this->post(route('cart.add', $urun->slug), ['miktar' => 1]);

        $this->post(route('checkout.store'), [
            ...$this->checkoutTestVerisi($urun->firma_id, [
                'musteri_ad_soyad' => 'Ali Veli',
                'musteri_email' => 'ali@example.com',
            ]),
        ])->assertRedirect(route('odeme.show', Siparis::query()->firstOrFail()));

        $this->post(route('odeme.basarili', Siparis::query()->firstOrFail()))
            ->assertRedirect(route('checkout.success'));

        $this->assertDatabaseCount('sepet_kalemleri', 0);
        $this->get(route('cart.index'))->assertSee('Sepetiniz boş');
    }
}
