<?php

namespace Tests\Feature\Restoran;

use App\Models\Rol;
use App\Models\Yetki;
use Database\Seeders\SaasPermissionsSeeder;
use Database\Seeders\SaasRolePermissionMatrixSeeder;
use Database\Seeders\SaasRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestoranYetkiSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_restoran_detay_yetkileri_seed_edilir(): void
    {
        $this->seed(SaasPermissionsSeeder::class);

        foreach ([
            'restoran_masa.goruntule',
            'restoran_masa.duzenle',
            'restoran_adisyon.goruntule',
            'restoran_adisyon.olustur',
            'restoran_adisyon.guncelle',
            'restoran_adisyon.iptal',
            'restoran_adisyon.tahsilat',
            'restoran_adisyon.fatura',
            'restoran_mutfak.goruntule',
            'restoran_mutfak.guncelle',
            'restoran_qr_menu.goruntule',
            'restoran_qr_menu.guncelle',
            'restoran_paket_servis.goruntule',
            'restoran_paket_servis.guncelle',
            'restoran_rapor.goruntule',
            'restoran_gun_sonu.goruntule',
            'restoran_ayar.guncelle',
        ] as $kod) {
            $this->assertDatabaseHas('yetkiler', [
                'kod' => $kod,
                'modul_kodu' => 'restoran',
            ]);
        }
    }

    public function test_rol_matrisi_restoran_operasyon_yetkilerini_dagitilir(): void
    {
        $this->seed(SaasPermissionsSeeder::class);
        $this->seed(SaasRolesSeeder::class);
        $this->seed(SaasRolePermissionMatrixSeeder::class);

        $tahsilatId = (int) Yetki::query()->where('kod', 'restoran_adisyon.tahsilat')->value('id');
        $faturaId = (int) Yetki::query()->where('kod', 'restoran_adisyon.fatura')->value('id');
        $mutfakGuncelleId = (int) Yetki::query()->where('kod', 'restoran_mutfak.guncelle')->value('id');
        $raporId = (int) Yetki::query()->where('kod', 'restoran_rapor.goruntule')->value('id');
        $gunSonuId = (int) Yetki::query()->where('kod', 'restoran_gun_sonu.goruntule')->value('id');

        $firmaSahibi = Rol::query()->where('kod', 'firma_sahibi')->firstOrFail();
        $muhasebePersoneli = Rol::query()->where('kod', 'muhasebe_personeli')->firstOrFail();
        $satisPersoneli = Rol::query()->where('kod', 'satis_personeli')->firstOrFail();
        $goruntuleyici = Rol::query()->where('kod', 'goruntuleyici')->firstOrFail();

        $this->assertTrue($firmaSahibi->yetkiler()->whereKey($tahsilatId)->exists());
        $this->assertTrue($firmaSahibi->yetkiler()->whereKey($faturaId)->exists());
        $this->assertTrue($firmaSahibi->yetkiler()->whereKey($gunSonuId)->exists());
        $this->assertTrue($muhasebePersoneli->yetkiler()->whereKey($gunSonuId)->exists());
        $this->assertTrue($muhasebePersoneli->yetkiler()->whereKey($faturaId)->exists());
        $this->assertTrue($satisPersoneli->yetkiler()->whereKey($mutfakGuncelleId)->exists());
        $this->assertTrue($goruntuleyici->yetkiler()->whereKey($raporId)->exists());
        $this->assertFalse($satisPersoneli->yetkiler()->whereKey($gunSonuId)->exists());
        $this->assertFalse($satisPersoneli->yetkiler()->whereKey($faturaId)->exists());
        $this->assertFalse($goruntuleyici->yetkiler()->whereKey($gunSonuId)->exists());
        $this->assertFalse($goruntuleyici->yetkiler()->whereKey($tahsilatId)->exists());
        $this->assertFalse($goruntuleyici->yetkiler()->whereKey($faturaId)->exists());
    }
}
