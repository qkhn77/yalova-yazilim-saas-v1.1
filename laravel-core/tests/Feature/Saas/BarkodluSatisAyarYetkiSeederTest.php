<?php

namespace Tests\Feature\Saas;

use App\Models\Rol;
use App\Models\Yetki;
use Database\Seeders\SaasPermissionsSeeder;
use Database\Seeders\SaasRolePermissionMatrixSeeder;
use Database\Seeders\SaasRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarkodluSatisAyarYetkiSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_barkodlu_satis_ayar_yetkileri_seed_edilir(): void
    {
        $this->seed(SaasPermissionsSeeder::class);

        $this->assertDatabaseHas('yetkiler', [
            'kod' => 'barkodlu_satis_ayar.goruntule',
            'modul_kodu' => 'barkodlu_satis',
            'eylem' => 'goruntule',
        ]);
        $this->assertDatabaseHas('yetkiler', [
            'kod' => 'barkodlu_satis_ayar.guncelle',
            'modul_kodu' => 'barkodlu_satis',
            'eylem' => 'guncelle',
        ]);
    }

    public function test_rol_matrisinde_ayar_yetkileri_beklenen_rollere_islenir(): void
    {
        $this->seed(SaasPermissionsSeeder::class);
        $this->seed(SaasRolesSeeder::class);
        $this->seed(SaasRolePermissionMatrixSeeder::class);

        $goruntuleId = (int) Yetki::query()->where('kod', 'barkodlu_satis_ayar.goruntule')->value('id');
        $guncelleId = (int) Yetki::query()->where('kod', 'barkodlu_satis_ayar.guncelle')->value('id');

        $firmaSahibi = Rol::query()->where('kod', 'firma_sahibi')->firstOrFail();
        $firmaYoneticisi = Rol::query()->where('kod', 'firma_yoneticisi')->firstOrFail();
        $goruntuleyici = Rol::query()->where('kod', 'goruntuleyici')->firstOrFail();

        $this->assertTrue($firmaSahibi->yetkiler()->whereKey($goruntuleId)->exists());
        $this->assertTrue($firmaSahibi->yetkiler()->whereKey($guncelleId)->exists());
        $this->assertTrue($firmaYoneticisi->yetkiler()->whereKey($goruntuleId)->exists());
        $this->assertTrue($firmaYoneticisi->yetkiler()->whereKey($guncelleId)->exists());
        $this->assertTrue($goruntuleyici->yetkiler()->whereKey($goruntuleId)->exists());
        $this->assertFalse($goruntuleyici->yetkiler()->whereKey($guncelleId)->exists());
    }

    public function test_firma_sahibi_tum_firma_yetkilerini_alir(): void
    {
        $this->seed(SaasRolesSeeder::class);
        $this->seed(SaasRolePermissionMatrixSeeder::class);

        $firmaSahibi = Rol::query()->where('kod', 'firma_sahibi')->firstOrFail();
        $tumYetkiIdleri = \App\Models\Yetki::query()->pluck('id')->sort()->values();
        $rolYetkiIdleri = $firmaSahibi->yetkiler()->pluck('yetkiler.id')->sort()->values();

        $this->assertSame($tumYetkiIdleri->all(), $rolYetkiIdleri->all());
    }
}
