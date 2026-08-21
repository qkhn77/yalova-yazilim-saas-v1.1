<?php

namespace Tests\Feature\Muhasebe;

use App\Filament\Clusters\Muhasebe\Pages\Cari;
use App\Filament\Clusters\Muhasebe\Pages\CariEkstreSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\CariHareketleriSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\CariYaslandirmaSayfasi;
use App\Filament\Clusters\Muhasebe\Resources\CariKartiKaynagi;
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

class MuhasebeCariSayfaErisimTest extends TestCase
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

    public function test_sadece_cari_guncelle_ise_cari_rapor_sayfalarina_erisilebilir(): void
    {
        $firma = $this->firmaVeMuhasebeModulu('MSE1');

        $yetki = Yetki::query()->create([
            'ad' => 'Cari güncelle',
            'kod' => MuhasebeYetkiSablonlari::CARI_GUNCELLE,
            'modul_kodu' => 'muhasebe',
            'eylem' => 'guncelle',
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

        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $this->assertTrue(Cari::canAccess());
        $this->assertTrue(CariEkstreSayfasi::canAccess());
        $this->assertTrue(CariYaslandirmaSayfasi::canAccess());
        $this->assertTrue(CariHareketleriSayfasi::canAccess());
    }

    public function test_cari_olusturma_sayfasi_tam_form_modunda_acilir(): void
    {
        $route = new \Illuminate\Routing\Route(['GET'], '/_test/cari-create-detay-modu', []);
        $route->name('tests.cari-karti.create');
        request()->setRouteResolver(fn () => $route);

        $this->assertTrue(CariKartiKaynagi::detayModu());
    }

    public function test_cari_duzenleme_sayfasi_tam_form_modunda_acilir(): void
    {
        $route = new \Illuminate\Routing\Route(['GET'], '/_test/cari-edit-detay-modu', []);
        $route->name('tests.cari-karti.edit');
        request()->setRouteResolver(fn () => $route);

        $this->assertTrue(CariKartiKaynagi::detayModu());
    }
}
