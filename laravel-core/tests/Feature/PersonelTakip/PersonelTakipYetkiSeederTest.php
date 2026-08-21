<?php

namespace Tests\Feature\PersonelTakip;

use App\Models\Rol;
use App\Models\Yetki;
use Database\Seeders\SaasPermissionsSeeder;
use Database\Seeders\SaasRolePermissionMatrixSeeder;
use Database\Seeders\SaasRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonelTakipYetkiSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_personel_takip_detay_yetkileri_seed_edilir(): void
    {
        $this->seed(SaasPermissionsSeeder::class);

        foreach ([
            'personel.goruntule',
            'personel.olustur',
            'personel.guncelle',
            'personel.sil',
            'personel_vardiya.goruntule',
            'personel_vardiya.duzenle',
            'personel_giris_cikis.goruntule',
            'personel_giris_cikis.duzenle',
            'personel_giris_cikis.onayla',
            'personel_izin.goruntule',
            'personel_izin.olustur',
            'personel_izin.duzenle',
            'personel_izin.onayla',
            'personel_avans.goruntule',
            'personel_avans.olustur',
            'personel_avans.onayla',
            'personel_maas.goruntule',
            'personel_maas.hesapla',
            'personel_maas.odeme_yap',
            'personel_rapor.goruntule',
        ] as $kod) {
            $this->assertDatabaseHas('yetkiler', [
                'kod' => $kod,
                'modul_kodu' => 'personel_takip',
            ]);
        }
    }

    public function test_firma_sahibi_maas_ve_avans_yetkilerini_alir_goruntuleyici_almaz(): void
    {
        $this->seed(SaasPermissionsSeeder::class);
        $this->seed(SaasRolesSeeder::class);
        $this->seed(SaasRolePermissionMatrixSeeder::class);

        $maasOdemeId = (int) Yetki::query()->where('kod', 'personel_maas.odeme_yap')->value('id');
        $avansOnayId = (int) Yetki::query()->where('kod', 'personel_avans.onayla')->value('id');
        $puantajOnayId = (int) Yetki::query()->where('kod', 'personel_giris_cikis.onayla')->value('id');
        $personelGoruntuleId = (int) Yetki::query()->where('kod', 'personel.goruntule')->value('id');

        $firmaSahibi = Rol::query()->where('kod', 'firma_sahibi')->firstOrFail();
        $goruntuleyici = Rol::query()->where('kod', 'goruntuleyici')->firstOrFail();

        $this->assertTrue($firmaSahibi->yetkiler()->whereKey($maasOdemeId)->exists());
        $this->assertTrue($firmaSahibi->yetkiler()->whereKey($avansOnayId)->exists());
        $this->assertTrue($firmaSahibi->yetkiler()->whereKey($puantajOnayId)->exists());
        $this->assertTrue($goruntuleyici->yetkiler()->whereKey($personelGoruntuleId)->exists());
        $this->assertFalse($goruntuleyici->yetkiler()->whereKey($maasOdemeId)->exists());
        $this->assertFalse($goruntuleyici->yetkiler()->whereKey($avansOnayId)->exists());
        $this->assertFalse($goruntuleyici->yetkiler()->whereKey($puantajOnayId)->exists());
    }
}
