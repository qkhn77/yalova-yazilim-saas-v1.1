<?php

namespace Tests\Feature\PersonelTakip;

use App\Models\Firma;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelDepartmani;
use App\Models\Personel\PersonelGorevi;
use App\Models\User;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonelTakipTenantScopeTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_personel_kayitlari_aktif_firma_ile_izole_edilir(): void
    {
        $firmaA = $this->firmaOlustur('PTA');
        $firmaB = $this->firmaOlustur('PTB');

        $personelA = Personel::withoutGlobalScopes()->create([
            'firma_id' => $firmaA->id,
            'ad_soyad' => 'Ayşe Yılmaz',
            'calisma_tipi' => 'tam_zamanli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 10000,
            'durum' => 'aktif',
        ]);
        $personelB = Personel::withoutGlobalScopes()->create([
            'firma_id' => $firmaB->id,
            'ad_soyad' => 'Mehmet Kaya',
            'calisma_tipi' => 'tam_zamanli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 12000,
            'durum' => 'aktif',
        ]);

        $kullanici = User::query()->create([
            'name' => 'Personel Kullanıcı',
            'email' => 'personel-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => false,
        ]);

        $this->actingAs($kullanici);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firmaA->id]);

        $this->assertNotNull(Personel::query()->find($personelA->id));
        $this->assertNull(Personel::query()->find($personelB->id));
    }

    public function test_departman_gorev_ve_personel_iliskileri_firma_kapsaminda_yuklenir(): void
    {
        $firma = $this->firmaOlustur('PTI');

        $departman = PersonelDepartmani::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad' => 'Operasyon',
            'kod' => 'OP',
            'aktif_mi' => true,
        ]);
        $gorev = PersonelGorevi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'departman_id' => $departman->id,
            'ad' => 'Teknisyen',
            'kod' => 'TEK',
            'aktif_mi' => true,
        ]);
        Personel::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'departman_id' => $departman->id,
            'gorev_id' => $gorev->id,
            'ad_soyad' => 'Zeynep Demir',
            'calisma_tipi' => 'tam_zamanli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 15000,
            'durum' => 'aktif',
        ]);

        $kullanici = User::query()->create([
            'name' => 'Personel Yetkili',
            'email' => 'personel-yetkili-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => false,
        ]);

        $this->actingAs($kullanici);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $this->assertSame(1, PersonelDepartmani::query()->withCount('personeller')->firstOrFail()->personeller_count);
        $this->assertSame('Teknisyen', Personel::query()->with('gorev')->firstOrFail()->gorev?->ad);
    }
}
