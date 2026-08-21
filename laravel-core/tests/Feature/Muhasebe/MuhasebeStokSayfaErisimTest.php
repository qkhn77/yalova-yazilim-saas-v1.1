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
use App\Models\Rol;
use App\Models\User;
use App\Models\Yetki;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MuhasebeStokSayfaErisimTest extends TestCase
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

    private function kullaniciVeYetkiOlustur(Firma $firma, string $yetkiKodu): User
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
            'ad' => 'Rol',
            'kod' => 'rol-'.uniqid(),
            'aciklama' => null,
            'sistem_rolu_mu' => false,
        ]);
        $rol->yetkiler()->attach($yetki->id);

        $user = User::query()->create([
            'name' => 'U',
            'email' => 'u-'.uniqid().'@test.local',
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

    public function test_sadece_stok_guncelle_ise_stok_rapor_sayfalarina_erisilebilir(): void
    {
        $firma = $this->firmaVeMuhasebeModulu('MSS1');
        $user = $this->kullaniciVeYetkiOlustur($firma, MuhasebeYetkiSablonlari::STOK_GUNCELLE);

        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $this->assertTrue(StokHareketleriSayfasi::canAccess());
        $this->assertTrue(KritikStoklarSayfasi::canAccess());
        $this->assertTrue(StokKartiKaynagi::canViewAny());
        $this->assertTrue(StokKategoriKaynagi::canViewAny());
    }

    public function test_sadece_stok_goruntule_ise_stok_sayfalari_ve_resource_erisimi_tutarlidir(): void
    {
        $firma = $this->firmaVeMuhasebeModulu('MSS2');
        $user = $this->kullaniciVeYetkiOlustur($firma, MuhasebeYetkiSablonlari::STOK_GORUNTULE);

        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $this->assertTrue(StokHareketleriSayfasi::canAccess());
        $this->assertTrue(KritikStoklarSayfasi::canAccess());
        $this->assertTrue(StokKartiKaynagi::canViewAny());
        $this->assertTrue(StokKategoriKaynagi::canViewAny());
    }

    public function test_sadece_stok_sil_ise_stok_sayfalari_ve_resource_erisimi_tutarlidir(): void
    {
        $firma = $this->firmaVeMuhasebeModulu('MSS3');
        $user = $this->kullaniciVeYetkiOlustur($firma, MuhasebeYetkiSablonlari::STOK_SIL);

        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $this->assertTrue(StokHareketleriSayfasi::canAccess());
        $this->assertTrue(KritikStoklarSayfasi::canAccess());
        $this->assertTrue(StokKartiKaynagi::canViewAny());
        $this->assertTrue(StokKategoriKaynagi::canViewAny());
    }
}
