<?php

namespace Tests\Feature\Muhasebe;

use App\Filament\Clusters\Muhasebe\Pages\FinansDashboardSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\FinansHareketleriListesiSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\TahsilatOlusturSayfasi;
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

class FinansFaz3UxTest extends TestCase
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

    public function test_finans_panel_ve_liste_finans_goruntule_ile_erisilebilir(): void
    {
        $firma = $this->firmaVeMuhasebeModulu('FF3A');

        $yetki = Yetki::query()->create([
            'ad' => 'Finans görüntüle',
            'kod' => MuhasebeYetkiSablonlari::FINANS_GORUNTULE,
            'modul_kodu' => 'muhasebe',
            'eylem' => 'goruntule',
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

        $this->assertTrue(FinansDashboardSayfasi::canAccess());
        $this->assertTrue(FinansHareketleriListesiSayfasi::canAccess());
        $this->assertFalse(TahsilatOlusturSayfasi::canAccess());
    }

    public function test_tahsilat_sayfasi_finans_olustur_ile_erisilebilir(): void
    {
        $firma = $this->firmaVeMuhasebeModulu('FF3B');

        $yetki = Yetki::query()->create([
            'ad' => 'Finans oluştur',
            'kod' => MuhasebeYetkiSablonlari::FINANS_OLUSTUR,
            'modul_kodu' => 'muhasebe',
            'eylem' => 'olustur',
        ]);

        $rol = Rol::query()->create([
            'ad' => 'Rol',
            'kod' => 'rol-'.uniqid(),
            'aciklama' => null,
            'sistem_rolu_mu' => false,
        ]);
        $rol->yetkiler()->attach($yetki->id);

        $user = User::query()->create([
            'name' => 'U2',
            'email' => 'u2-'.uniqid().'@test.local',
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

        $this->assertTrue(TahsilatOlusturSayfasi::canAccess());
    }
}
