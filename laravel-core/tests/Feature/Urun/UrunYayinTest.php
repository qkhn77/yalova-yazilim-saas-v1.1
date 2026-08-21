<?php

namespace Tests\Feature\Urun;

use App\Models\Firma;
use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokKategorisi;
use App\Modules\Urun\Servisler\UrunServisi;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unpublished-web')]
class UrunYayinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Front-end tarafinda tenant scope aktif olmasin; servis zaten tenantScopeOlmadan ile fallback yapiyor.
        app(TenantContextService::class)->temizle();
        // Testler sırasında istek URI'sine "/yalova-kamera" prefix'i eklenip route eşleşmesini bozmasin.
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    private function firmaOlustur(string $kod): Firma
    {
        return Firma::query()->create([
            'ad' => 'Firma '.$kod,
            'kisa_ad' => $kod,
            'firma_kodu' => $kod.'-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
    }

    private function tanimOlustur(Firma $firma, string $kategoriKod): array
    {
        $kategori = StokKategorisi::query()->create([
            'firma_id' => $firma->id,
            'kod' => $kategoriKod,
            'ad' => 'Kategori '.$kategoriKod,
            'aktif_mi' => true,
            'is_sabit' => false,
        ]);

        $birim = Birim::tenantScopeOlmadan(fn () => Birim::query()
            ->where('firma_id', $firma->id)
            ->whereRaw('UPPER(kod) = UPPER(?)', ['AD'])
            ->first());
        if (! $birim) {
            $birim = Birim::query()->create([
                'firma_id' => $firma->id,
                'kod' => 'AD',
                'ad' => 'Adet',
                'aktif_mi' => true,
                'is_sabit' => false,
            ]);
        }

        return ['kategori' => $kategori, 'birim' => $birim];
    }

    public function test_eticaret_urunleri_listelemede_gorunur(): void
    {
        $firma = $this->firmaOlustur('U1');
        $t = $this->tanimOlustur($firma, 'KAT1');

        StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'STK-U1-1',
            'ad' => 'E-Ticaret Ürün',
            'slug' => 'e-ticaret-urun',
            'tur' => StokKartiTuru::ETicaret->value,
            'durum' => HesapDurumu::Aktif->value,
            'kategori_id' => $t['kategori']->id,
            'kategori_kodu' => $t['kategori']->kod,
            'birim' => $t['birim']->kod,
            'satis_fiyati' => 100,
            'stok_takip' => true,
            'stok_miktari' => 1,
            'kdv_orani' => 20,
        ]);

        $urunler = app(UrunServisi::class)->listele([]);
        $this->assertTrue($urunler->getCollection()->contains(fn (StokKarti $u) => $u->slug === 'e-ticaret-urun'));

        $res = $this->get(route('products.index'));
        $res->assertStatus(200);

        $res->assertSee('E-Ticaret Ürün');
    }

    public function test_slug_ile_detay_acilir(): void
    {
        $firma = $this->firmaOlustur('U2');
        $t = $this->tanimOlustur($firma, 'KAT1');

        StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'STK-U2-1',
            'ad' => 'Detay Ürün',
            'slug' => 'detay-urun',
            'tur' => StokKartiTuru::ETicaret->value,
            'durum' => HesapDurumu::Aktif->value,
            'kategori_id' => $t['kategori']->id,
            'kategori_kodu' => $t['kategori']->kod,
            'birim' => $t['birim']->kod,
            'satis_fiyati' => 120,
            'stok_takip' => true,
            'stok_miktari' => 2,
            'kdv_orani' => 20,
        ]);

        $urun = app(UrunServisi::class)->detay('detay-urun');
        $this->assertSame('Detay Ürün', $urun->ad);

        $res = $this->get(route('products.show', 'detay-urun'));
        $res->assertStatus(200);
        $res->assertSee('Detay Ürün');
    }

    public function test_slug_yoksa_404_doner(): void
    {
        $res = $this->get(route('products.show', 'boyle-bir-urun-yok'));
        $res->assertStatus(404);
    }

    public function test_stokta_yok_detayda_dogruluk(): void
    {
        $firma = $this->firmaOlustur('U3');
        $t = $this->tanimOlustur($firma, 'KAT1');

        StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'STK-U3-1',
            'ad' => 'Stok Yok Ürün',
            'slug' => 'stok-yok-urun',
            'tur' => StokKartiTuru::ETicaret->value,
            'durum' => HesapDurumu::Aktif->value,
            'kategori_id' => $t['kategori']->id,
            'kategori_kodu' => $t['kategori']->kod,
            'birim' => $t['birim']->kod,
            'satis_fiyati' => 150,
            'stok_takip' => true,
            'stok_miktari' => 0,
            'kdv_orani' => 20,
        ]);

        $res = $this->get(route('products.show', 'stok-yok-urun'));
        $res->assertStatus(200);
        $res->assertSee(__('front.product.out_of_stock'));
    }

    public function test_kategori_filtreleme_calisir(): void
    {
        $firma = $this->firmaOlustur('U4');
        $t1 = $this->tanimOlustur($firma, 'KAT1');
        $t2 = $this->tanimOlustur($firma, 'KAT2');

        StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'STK-U4-1',
            'ad' => 'Kategori 1 Ürün',
            'slug' => 'kat1-urun',
            'tur' => StokKartiTuru::ETicaret->value,
            'durum' => HesapDurumu::Aktif->value,
            'kategori_id' => $t1['kategori']->id,
            'kategori_kodu' => $t1['kategori']->kod,
            'birim' => $t1['birim']->kod,
            'satis_fiyati' => 100,
            'stok_takip' => false,
            'stok_miktari' => 0,
            'kdv_orani' => 20,
        ]);

        StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'STK-U4-2',
            'ad' => 'Kategori 2 Ürün',
            'slug' => 'kat2-urun',
            'tur' => StokKartiTuru::ETicaret->value,
            'durum' => HesapDurumu::Aktif->value,
            'kategori_id' => $t2['kategori']->id,
            'kategori_kodu' => $t2['kategori']->kod,
            'birim' => $t2['birim']->kod,
            'satis_fiyati' => 200,
            'stok_takip' => true,
            'stok_miktari' => 1,
            'kdv_orani' => 20,
        ]);

        $kategoriSlug = (string) StokKategorisi::tenantScopeOlmadan(
            fn () => StokKategorisi::query()->findOrFail($t2['kategori']->id)->slug
        );
        $res = $this->get(route('products.category', ['slug' => $kategoriSlug]));
        $res->assertStatus(200);
        $res->assertSee('Kategori 2 Ürün');
        $res->assertDontSee('Kategori 1 Ürün');
    }

    public function test_sitemap_urunleri_icerir(): void
    {
        Cache::flush();

        $firma = $this->firmaOlustur('U5');
        $t = $this->tanimOlustur($firma, 'KAT1');

        StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'STK-U5-1',
            'ad' => 'Sitemap Ürün',
            'slug' => 'sitemap-urun',
            'tur' => StokKartiTuru::ETicaret->value,
            'durum' => HesapDurumu::Aktif->value,
            'kategori_id' => $t['kategori']->id,
            'kategori_kodu' => $t['kategori']->kod,
            'birim' => $t['birim']->kod,
            'satis_fiyati' => 180,
            'stok_takip' => true,
            'stok_miktari' => 1,
            'kdv_orani' => 20,
        ]);

        $res = $this->get('/sitemap.xml');
        $res->assertStatus(200);
        $res->assertSee('/urun/sitemap-urun', false);
    }

    public function test_slug_save_sirasinda_olusur(): void
    {
        $firma = $this->firmaOlustur('U6');
        $t = $this->tanimOlustur($firma, 'KAT3');

        $stok = StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'STK-U6-1',
            'ad' => 'Slug Otomatik Urun',
            'slug' => '',
            'tur' => StokKartiTuru::ETicaret->value,
            'durum' => HesapDurumu::Aktif->value,
            'kategori_id' => $t['kategori']->id,
            'kategori_kodu' => $t['kategori']->kod,
            'birim' => $t['birim']->kod,
            'satis_fiyati' => 99,
            'stok_takip' => false,
            'stok_miktari' => 0,
            'kdv_orani' => 20,
        ]);

        $this->assertNotNull($stok->slug);
        $this->assertSame('slug-otomatik-urun', $stok->slug);
    }

    public function test_pagination_calisir(): void
    {
        $firma = $this->firmaOlustur('U7');
        $t = $this->tanimOlustur($firma, 'KAT4');

        for ($i = 1; $i <= 13; $i++) {
            StokKarti::query()->create([
                'firma_id' => $firma->id,
                'kod' => 'STK-U7-'.$i,
                'ad' => 'Paged Urun '.$i,
                'slug' => 'paged-urun-'.$i,
                'tur' => StokKartiTuru::ETicaret->value,
                'durum' => HesapDurumu::Aktif->value,
                'kategori_id' => $t['kategori']->id,
                'kategori_kodu' => $t['kategori']->kod,
                'birim' => $t['birim']->kod,
                'satis_fiyati' => 10 + $i,
                'stok_takip' => false,
                'stok_miktari' => 0,
                'kdv_orani' => 20,
            ]);
        }

        $ilk = $this->get(route('products.index'));
        $ilk->assertStatus(200);
        $ilk->assertSee('Paged Urun 13');
        $ilk->assertDontSee('href="http://localhost/urun/paged-urun-1"');

        $ikinci = $this->get(route('products.index', ['page' => 2]));
        $ikinci->assertStatus(200);
        $ikinci->assertSee('Paged Urun 1');
    }

    public function test_kategori_slug_calisir(): void
    {
        $firma = $this->firmaOlustur('U8');
        $t = $this->tanimOlustur($firma, 'KAT5');

        StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'STK-U8-1',
            'ad' => 'Slug Kategori Urun',
            'slug' => 'slug-kategori-urun',
            'tur' => StokKartiTuru::ETicaret->value,
            'durum' => HesapDurumu::Aktif->value,
            'kategori_id' => $t['kategori']->id,
            'kategori_kodu' => $t['kategori']->kod,
            'birim' => $t['birim']->kod,
            'satis_fiyati' => 200,
            'stok_takip' => true,
            'stok_miktari' => 1,
            'kdv_orani' => 20,
        ]);

        $kategoriSlug = (string) StokKategorisi::tenantScopeOlmadan(
            fn () => StokKategorisi::query()->findOrFail($t['kategori']->id)->slug
        );
        $res = $this->get(route('products.category', ['slug' => $kategoriSlug]));
        $res->assertStatus(200);
        $res->assertSee('Slug Kategori Urun');
    }

    public function test_cache_temizlenir(): void
    {
        Cache::forever('sitemap_xml', 'dummy');
        Cache::forever('urun_liste_cache_version', 5);

        $firma = $this->firmaOlustur('U9');
        $t = $this->tanimOlustur($firma, 'KAT6');

        StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'STK-U9-1',
            'ad' => 'Cache Test Urun',
            'slug' => 'cache-test-urun',
            'tur' => StokKartiTuru::ETicaret->value,
            'durum' => HesapDurumu::Aktif->value,
            'kategori_id' => $t['kategori']->id,
            'kategori_kodu' => $t['kategori']->kod,
            'birim' => $t['birim']->kod,
            'satis_fiyati' => 250,
            'stok_takip' => true,
            'stok_miktari' => 1,
            'kdv_orani' => 20,
        ]);

        $this->assertNull(Cache::get('sitemap_xml'));
        $this->assertSame(6, (int) Cache::get('urun_liste_cache_version'));
    }
}
