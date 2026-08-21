<?php

namespace Tests\Feature\Restoran;

use App\Models\Firma;
use App\Models\Muhasebe\StokKarti;
use App\Models\Restoran\RestoranMenuKategorisi;
use App\Models\Restoran\RestoranMenuUrunu;
use App\Models\Sube;
use App\Models\User;
use App\Services\Restoran\RestoranQrMenuServisi;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RestoranQrMenuServisiTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_kategorileri_aktif_firma_ile_scope_edilir(): void
    {
        $firmaA = $this->firmaOlustur('RQA');
        $firmaB = $this->firmaOlustur('RQB');

        RestoranMenuKategorisi::withoutGlobalScopes()->create([
            'firma_id' => $firmaA->id,
            'ad' => 'Ana Yemek',
        ]);
        RestoranMenuKategorisi::withoutGlobalScopes()->create([
            'firma_id' => $firmaB->id,
            'ad' => 'Tatlı',
        ]);

        $this->actingAs(User::factory()->create());
        app(TenantContextService::class)->firmaAyarla($firmaA);

        $this->assertSame(['Ana Yemek'], RestoranMenuKategorisi::query()->pluck('ad')->all());

        app(TenantContextService::class)->firmaAyarla($firmaB);

        $this->assertSame(['Tatlı'], RestoranMenuKategorisi::query()->pluck('ad')->all());
    }

    public function test_menu_urunu_stoktan_ad_fiyat_ve_kdv_alir(): void
    {
        $firma = $this->firmaOlustur('RQS');
        $kategori = RestoranMenuKategorisi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad' => 'İçecek',
        ]);
        $stok = StokKarti::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kod' => 'ICE-1',
            'ad' => 'Limonata',
            'tur' => 'ticari_mal',
            'birim' => 'AD',
            'satis_fiyati' => 45,
            'kdv_orani' => 10,
            'durum' => 'aktif',
        ]);

        $urun = RestoranMenuUrunu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kategori_id' => $kategori->id,
            'stok_karti_id' => $stok->id,
        ]);

        $this->assertSame('Limonata', $urun->ad);
        $this->assertSame('45.00', (string) $urun->fiyat);
        $this->assertSame('10.00', (string) $urun->kdv_orani);
    }

    public function test_menu_urunu_firma_disi_kategoriye_baglanamaz(): void
    {
        $firma = $this->firmaOlustur('RQF');
        $digerFirma = $this->firmaOlustur('RQD');
        $kategori = RestoranMenuKategorisi::withoutGlobalScopes()->create([
            'firma_id' => $digerFirma->id,
            'ad' => 'Dış Kategori',
        ]);

        $this->expectException(ValidationException::class);

        RestoranMenuUrunu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kategori_id' => $kategori->id,
            'ad' => 'Hatalı Ürün',
            'fiyat' => 10,
        ]);
    }

    public function test_qr_menu_sadece_gorunur_aktif_ve_stokta_urunleri_dondurur(): void
    {
        $firma = $this->firmaOlustur('RQG');
        $sube = $this->subeOlustur($firma, 'MRK');
        $kategori = RestoranMenuKategorisi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'ad' => 'Menü',
        ]);

        RestoranMenuUrunu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kategori_id' => $kategori->id,
            'ad' => 'Görünür Ürün',
            'fiyat' => 100,
            'siralama' => 1,
        ]);
        RestoranMenuUrunu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kategori_id' => $kategori->id,
            'ad' => 'Gizli Ürün',
            'fiyat' => 100,
            'qr_menu_gorunur_mu' => false,
        ]);
        RestoranMenuUrunu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kategori_id' => $kategori->id,
            'ad' => 'Stokta Yok',
            'fiyat' => 100,
            'stokta_var_mi' => false,
        ]);

        $menu = app(RestoranQrMenuServisi::class)->gorunurMenu((int) $firma->id, (int) $sube->id);

        $this->assertCount(1, $menu);
        $this->assertSame('Menü', $menu->first()->ad);
        $this->assertSame(['Görünür Ürün'], $menu->first()->urunler->pluck('ad')->all());
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
}
