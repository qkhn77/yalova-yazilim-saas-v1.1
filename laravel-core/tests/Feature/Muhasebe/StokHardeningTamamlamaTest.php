<?php

namespace Tests\Feature\Muhasebe;

use App\Filament\Clusters\Muhasebe\Pages\KritikStoklarSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\StokHareketleriSayfasi;
use App\Filament\Clusters\Muhasebe\Resources\StokKartiKaynagi;
use App\Filament\Clusters\Muhasebe\Resources\StokKategoriKaynagi;
use App\Models\Firma;
use App\Models\FirmaKullanici;
use App\Models\FirmaModulu;
use App\Models\Modul;
use App\Models\Muhasebe\StokHareketi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokKategorisi;
use App\Models\Rol;
use App\Models\User;
use App\Models\Yetki;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokBelgeTuru;
use App\Muhasebe\Enumlar\StokHareketDurumu;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StokHardeningTamamlamaTest extends TestCase
{
    use RefreshDatabase;

    private function firmaVeMuhasebeModulu(string $kod): Firma
    {
        $firma = Firma::query()->create([
            'ad' => 'Test '.$kod,
            'kisa_ad' => $kod,
            'firma_kodu' => $kod.'-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);

        $modul = Modul::query()->firstOrCreate(
            ['kod' => 'muhasebe'],
            ['ad' => 'Muhasebe', 'aciklama' => null, 'aktif_mi' => true, 'siralama' => 1]
        );

        FirmaModulu::query()->create([
            'firma_id' => $firma->id,
            'modul_id' => $modul->id,
            'durum' => 'aktif',
        ]);

        return $firma;
    }

    private function firmaKullanicisiOlustur(Firma $firma, string $yetkiKodu): User
    {
        $yetki = Yetki::query()->create([
            'ad' => $yetkiKodu,
            'kod' => $yetkiKodu,
            'modul_kodu' => 'muhasebe',
            'eylem' => str_contains($yetkiKodu, 'goruntule')
                ? 'goruntule'
                : (str_contains($yetkiKodu, 'sil') ? 'sil' : 'guncelle'),
        ]);

        $rol = Rol::query()->create([
            'ad' => 'Rol-'.$yetkiKodu,
            'kod' => 'rol-'.uniqid(),
            'aciklama' => null,
            'sistem_rolu_mu' => false,
        ]);
        $rol->yetkiler()->attach($yetki->id);

        $user = User::query()->create([
            'name' => 'U-'.$yetkiKodu,
            'email' => uniqid($yetkiKodu.'-').'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => false,
        ]);

        FirmaKullanici::query()->withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kullanici_id' => $user->id,
            'rol_id' => $rol->id,
            'durum' => 'aktif',
            'varsayilan_firma_mi' => true,
        ]);

        return $user;
    }

    public function test_tenant_sadece_kendi_kategorisini_gorur(): void
    {
        $fa = $this->firmaVeMuhasebeModulu('KAT1');
        $fb = $this->firmaVeMuhasebeModulu('KAT2');
        $user = $this->firmaKullanicisiOlustur($fa, MuhasebeYetkiSablonlari::STOK_GORUNTULE);

        $kategoriB = StokKategorisi::query()->create([
            'firma_id' => $fb->id,
            'kod' => 'KB-'.uniqid(),
            'ad' => 'Kategori B',
            'aktif_mi' => true,
        ]);

        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $fa->id]);

        $this->assertNull(StokKategorisi::query()->find($kategoriB->id));
    }

    public function test_baska_firmanin_kategorisi_stok_formunda_kullanilamaz(): void
    {
        $fa = $this->firmaVeMuhasebeModulu('SKF1');
        $fb = $this->firmaVeMuhasebeModulu('SKF2');

        $kategoriB = StokKategorisi::query()->create([
            'firma_id' => $fb->id,
            'kod' => 'KB-'.uniqid(),
            'ad' => 'Kategori B',
            'aktif_mi' => true,
        ]);

        $this->expectException(ValidationException::class);
        StokKartiKaynagi::kategoriDegerleriniHazirla((int) $fa->id, (int) $kategoriB->id);
    }

    public function test_stok_hareketi_listesi_tenantta_sinirli(): void
    {
        $fa = $this->firmaVeMuhasebeModulu('HR1');
        $fb = $this->firmaVeMuhasebeModulu('HR2');
        $user = $this->firmaKullanicisiOlustur($fa, MuhasebeYetkiSablonlari::STOK_GORUNTULE);

        $stokB = StokKarti::query()->create([
            'firma_id' => $fb->id,
            'kod' => 'SB-'.uniqid(),
            'ad' => 'Stok B',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);
        $hareketB = StokHareketi::query()->create([
            'firma_id' => $fb->id,
            'stok_id' => $stokB->id,
            'islem_turu' => StokHareketIslemTuru::Alis,
            'miktar' => '1',
            'onceki_miktar' => '0',
            'sonraki_miktar' => '1',
            'birim_fiyat' => '10',
            'birim_maliyet' => '10',
            'toplam' => '10',
            'toplam_maliyet' => '10',
            'belge_turu' => StokBelgeTuru::Fatura,
            'referans_tipi' => StokBelgeTuru::Fatura->value,
            'belge_id' => 11,
            'referans_id' => 11,
            'tarih' => now(),
            'islem_tarihi' => now(),
            'durum' => StokHareketDurumu::Aktif,
        ]);

        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $fa->id]);

        $ids = StokHareketleriSayfasi::stokHareketSorgusu()->pluck('id')->all();
        $this->assertNotContains($hareketB->id, $ids);
    }

    public function test_kritik_stok_sorgusu_kurala_uygun_kayitlari_getirir(): void
    {
        $f = $this->firmaVeMuhasebeModulu('KR1');
        $user = $this->firmaKullanicisiOlustur($f, MuhasebeYetkiSablonlari::STOK_GORUNTULE);
        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $f->id]);

        $kritik = StokKarti::query()->create([
            'firma_id' => $f->id,
            'kod' => 'K-'.uniqid(),
            'ad' => 'Kritik',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'stok_takip' => true,
            'minimum_stok' => '5',
            'stok_miktari' => '3',
            'para_birimi' => 'TRY',
        ]);
        $normal = StokKarti::query()->create([
            'firma_id' => $f->id,
            'kod' => 'N-'.uniqid(),
            'ad' => 'Normal',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'stok_takip' => true,
            'minimum_stok' => '5',
            'stok_miktari' => '8',
            'para_birimi' => 'TRY',
        ]);

        $ids = KritikStoklarSayfasi::kritikStokSorgusu()->pluck('id')->all();
        $this->assertContains($kritik->id, $ids);
        $this->assertNotContains($normal->id, $ids);
    }

    public function test_sidebar_page_resource_edge_case_sadece_stok_sil_ile_tutarlidir(): void
    {
        $firma = $this->firmaVeMuhasebeModulu('EDG1');
        $user = $this->firmaKullanicisiOlustur($firma, MuhasebeYetkiSablonlari::STOK_SIL);

        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $this->assertTrue(StokHareketleriSayfasi::canAccess());
        $this->assertTrue(KritikStoklarSayfasi::canAccess());
        $this->assertTrue(StokKartiKaynagi::canViewAny());
        $this->assertTrue(StokKategoriKaynagi::canViewAny());
    }

    public function test_kategori_parent_ayni_firma_kurali_calisir(): void
    {
        $fa = $this->firmaVeMuhasebeModulu('PR1');
        $fb = $this->firmaVeMuhasebeModulu('PR2');

        $parentB = StokKategorisi::query()->create([
            'firma_id' => $fb->id,
            'kod' => 'PB-'.uniqid(),
            'ad' => 'Parent B',
            'aktif_mi' => true,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Üst kategori bu firmaya ait veya sabit olmalıdır.');
        StokKategoriKaynagi::parentKategoriIdHazirla((int) $fa->id, false, (int) $parentB->id, null);
    }

    public function test_kategori_dongusu_obvious_cycle_engellenir(): void
    {
        $f = $this->firmaVeMuhasebeModulu('CY1');
        $a = StokKategorisi::query()->create([
            'firma_id' => $f->id,
            'kod' => 'A-'.uniqid(),
            'ad' => 'A',
            'aktif_mi' => true,
        ]);
        $b = StokKategorisi::query()->create([
            'firma_id' => $f->id,
            'parent_id' => $a->id,
            'kod' => 'B-'.uniqid(),
            'ad' => 'B',
            'aktif_mi' => true,
        ]);
        $c = StokKategorisi::query()->create([
            'firma_id' => $f->id,
            'parent_id' => $b->id,
            'kod' => 'C-'.uniqid(),
            'ad' => 'C',
            'aktif_mi' => true,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('döngüsel kategori');
        StokKategoriKaynagi::parentKategoriIdHazirla((int) $f->id, false, (int) $c->id, (int) $a->id);
    }
}
