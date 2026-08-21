<?php

namespace Tests\Feature\Muhasebe;

use App\Filament\Clusters\Muhasebe\Resources\StokKartiKaynagi\Pages\CreateStokKarti;
use App\Models\Firma;
use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\MuhasebeLogoTuru;
use App\Models\Muhasebe\MuhasebeMalzemeTuru;
use App\Models\Muhasebe\MuhasebeMarka;
use App\Models\Muhasebe\MuhasebeStokModeli;
use App\Models\Muhasebe\MuhasebeTasarim;
use App\Models\Muhasebe\MuhasebeVaryant;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokKategorisi;
use App\Models\Muhasebe\VergiOrani;
use App\Models\User;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class StokSistemFullModelTest extends TestCase
{
    use RefreshDatabase;

    private function firmaOlustur(string $kod): Firma
    {
        return Firma::query()->create([
            'ad' => 'F '.$kod,
            'kisa_ad' => $kod,
            'firma_kodu' => $kod.'-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
    }

    private function tanimlarOlustur(Firma $firma): array
    {
        $kategori = StokKategorisi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'KAT-'.uniqid(),
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

        $vergi = VergiOrani::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'KDV',
            'ad' => 'KDV',
            'oran' => 20,
            'aktif_mi' => true,
            'is_sabit' => false,
        ]);

        $marka = MuhasebeMarka::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'MRK',
            'ad' => 'Marka',
            'aktif_mi' => true,
            'is_sabit' => false,
        ]);

        $model = MuhasebeStokModeli::query()->create([
            'firma_id' => $firma->id,
            'marka_id' => $marka->id,
            'kod' => 'MOD',
            'ad' => 'Model',
            'aktif_mi' => true,
            'is_sabit' => false,
        ]);

        $tasarim = MuhasebeTasarim::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'TSM',
            'ad' => 'Tasarim',
            'aktif_mi' => true,
            'is_sabit' => false,
        ]);

        $malzeme = MuhasebeMalzemeTuru::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'MLZ',
            'ad' => 'Malzeme',
            'aktif_mi' => true,
            'is_sabit' => false,
        ]);

        $logo = MuhasebeLogoTuru::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'LOG',
            'ad' => 'Logo',
            'aktif_mi' => true,
            'is_sabit' => false,
        ]);

        $varyant = MuhasebeVaryant::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'VY',
            'ad' => 'Varyant',
            'aktif_mi' => true,
            'is_sabit' => false,
        ]);

        $tedarikci = Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'T-'.uniqid(),
            'ad' => 'Tedarikci',
            'tur' => CariTuru::Tedarikci->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        return [
            'kategori' => $kategori,
            'birim' => $birim,
            'vergi' => $vergi,
            'marka' => $marka,
            'model' => $model,
            'tasarim' => $tasarim,
            'malzeme' => $malzeme,
            'logo' => $logo,
            'varyant' => $varyant,
            'tedarikci' => $tedarikci,
        ];
    }

    public function test_stok_olusturma_tum_alanlar(): void
    {
        $firma = $this->firmaOlustur('A');
        $t = $this->tanimlarOlustur($firma);

        $stok = StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'STK000001',
            'sku' => 'SKU-TELEFON-X',
            'upc' => '012345678905',
            'ean' => '5901234123457',
            'gtin' => '05901234123457',
            'mpn' => 'MPN-TX-01',
            'amazon_asin' => 'B0TEST1234',
            'fba_kodu' => 'FBA-TX-01',
            'ad' => 'Telefon X',
            'slug' => 'telefon-x',
            'kisa_ad' => 'Tx',
            'barkod' => 'B-1',
            'seri_no' => 'SR-1',
            'imei_no' => 'IMEI-1',
            'tur' => StokKartiTuru::TicariMal->value,
            'kategori_id' => $t['kategori']->id,
            'kategori_kodu' => $t['kategori']->kod,
            'birim' => $t['birim']->kod,
            'alis_fiyati' => 100,
            'satis_fiyati' => 120,
            'indirimli_fiyat' => 110,
            'para_birimi' => 'TRY',
            'kdv_orani' => 20,
            'gumruk_orani' => 0,
            'kritik_seviye_miktar' => 5,
            'aciklama' => 'Aciklama',
            'durum' => HesapDurumu::Aktif->value,
            'stok_takip' => true,
            'minimum_stok' => 1,
            'maksimum_stok' => 100,
            'stok_miktari' => 10,
            'depo_id' => 1,
            'marka_id' => $t['marka']->id,
            'model_id' => $t['model']->id,
            'tasarim_id' => $t['tasarim']->id,
            'malzeme_turu_id' => $t['malzeme']->id,
            'logo_turu_id' => $t['logo']->id,
            'varyant_id' => $t['varyant']->id,
            'tedarikci_id' => $t['tedarikci']->id,
            'agirlik' => 0.5,
            'hacim' => 0.001,
            'kargo_sinifi' => 'A',
            'satis_adedi' => 2,
            'goruntulenme_sayisi' => 10,
            'seo_title' => 'Seo',
            'seo_description' => 'Seo aciklama',
            'seo_keywords' => 'seo,kelime',
            'og_gorsel' => 'stok/og/og.jpg',
            'og_baslik' => 'OG baslik',
            'og_aciklama' => 'OG aciklama',
            'og_etiket' => 'OG etik',
        ]);

        $this->assertNotNull($stok->id);
        $stok->gorseller()->createMany([
            ['dosya_yolu' => 'stok/gallery/1.jpg', 'sira' => 1, 'kapak_mi' => true, 'aktif_mi' => true],
            ['dosya_yolu' => 'stok/gallery/2.jpg', 'sira' => 2, 'kapak_mi' => false, 'aktif_mi' => true],
        ]);
        $stok->load('gorseller');

        $this->assertSame('telefon-x', $stok->slug);
        $this->assertSame('SKU-TELEFON-X', $stok->sku);
        $this->assertSame('B0TEST1234', $stok->amazon_asin);
        $this->assertSame('FBA-TX-01', $stok->fba_kodu);
        $this->assertSame('stok/gallery/2.jpg', $stok->galeri_gorsel_yollari[1]);
        $this->assertSame((int) $t['marka']->id, (int) $stok->marka_id);
    }

    public function test_kod_otomatik_uretimi_create_mutatorundan(): void
    {
        $firma = $this->firmaOlustur('B');

        /** @var User $user */
        $user = User::factory()->create(['super_admin_mi' => true]);
        $this->actingAs($user);

        $page = app(CreateStokKarti::class);

        $method = new ReflectionMethod(CreateStokKarti::class, 'mutateFormDataBeforeCreate');
        $method->setAccessible(true);

        $data = [
            'firma_id' => $firma->id,
            'kod' => '',
            'ad' => 'Stok',
            'tur' => StokKartiTuru::TicariMal->value,
            'birim' => 'AD',
            'satis_fiyati' => 10,
        ];

        $out = $method->invoke($page, $data);

        $this->assertSame('STK000001', $out['kod']);
    }

    public function test_hizli_ekleme_firma_tanimi_uretir(): void
    {
        $firma = $this->firmaOlustur('C');

        $marka = MuhasebeMarka::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'MX',
            'ad' => 'MarkaX',
            'aktif_mi' => true,
            'is_sabit' => false,
        ]);

        $this->assertFalse((bool) $marka->is_sabit);
        $this->assertSame($firma->id, (int) $marka->firma_id);
        $this->assertSame($firma->id, (int) $marka->tanim_firma_kapsami);
    }

    public function test_tenant_izolasyonu_stok_kartinda(): void
    {
        $fa = $this->firmaOlustur('TA');
        $fb = $this->firmaOlustur('TB');

        $stokA = StokKarti::query()->create([
            'firma_id' => $fa->id,
            'kod' => 'STK-A',
            'ad' => 'A',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'para_birimi' => 'TRY',
            'stok_takip' => true,
            'stok_miktari' => 1,
            'birim' => 'AD',
            'satis_fiyati' => 1,
        ]);

        $stokB = StokKarti::query()->create([
            'firma_id' => $fb->id,
            'kod' => 'STK-B',
            'ad' => 'B',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'para_birimi' => 'TRY',
            'stok_takip' => true,
            'stok_miktari' => 1,
            'birim' => 'AD',
            'satis_fiyati' => 1,
        ]);

        /** @var User $user */
        $user = User::factory()->create(['super_admin_mi' => false]);
        $this->actingAs($user);

        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $fa->id]);
        $this->assertSame(1, StokKarti::query()->count());
        $this->assertTrue(StokKarti::query()->whereKey($stokA->id)->exists());
        $this->assertFalse(StokKarti::query()->whereKey($stokB->id)->exists());
    }

    public function test_stok_guncelleme(): void
    {
        $firma = $this->firmaOlustur('D');
        /** @var User $admin */
        $admin = User::factory()->create(['super_admin_mi' => true]);
        $this->actingAs($admin);
        $stok = StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'STK-D1',
            'ad' => 'D1',
            'slug' => 'd1',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'para_birimi' => 'TRY',
            'stok_takip' => true,
            'stok_miktari' => 1,
            'birim' => 'AD',
            'satis_fiyati' => 1,
        ]);

        $stok->update(['stok_miktari' => 5]);

        $fresh = $stok->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(5.0, (float) $fresh->stok_miktari);
    }
}
