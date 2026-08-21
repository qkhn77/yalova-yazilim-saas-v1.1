<?php

namespace Tests\Feature\PersonelTakip;

use App\Filament\Clusters\PersonelTakip\Resources\PersonelKaynagi;
use App\Models\Firma;
use App\Models\FirmaKullanici;
use App\Models\FirmaModulu;
use App\Models\Modul;
use App\Models\Personel\Personel;
use App\Models\Rol;
use App\Models\User;
use App\Models\Yetki;
use App\Services\ModulErisimService;
use App\Services\SidebarService;
use App\Services\TenantContextService;
use App\Services\YetkiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PersonelTakipTenantVeSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_personel_kayitlari_aktif_firma_ile_scope_edilir(): void
    {
        $firmaA = $this->firmaOlustur('Firma A', 'firma-a');
        $firmaB = $this->firmaOlustur('Firma B', 'firma-b');

        $this->personelOlustur($firmaA, 'Firma A Personeli');
        $this->personelOlustur($firmaB, 'Firma B Personeli');

        $user = $this->userOlustur();
        $this->actingAs($user);
        app(TenantContextService::class)->firmaAyarla($firmaA);

        $this->assertSame(['Firma A Personeli'], Personel::query()->pluck('ad_soyad')->all());

        app(TenantContextService::class)->firmaAyarla($firmaB);

        $this->assertSame(['Firma B Personeli'], Personel::query()->pluck('ad_soyad')->all());
    }

    public function test_super_admin_personel_scope_disinda_tum_firmalari_gorebilir(): void
    {
        $firmaA = $this->firmaOlustur('Firma A', 'firma-a');
        $firmaB = $this->firmaOlustur('Firma B', 'firma-b');

        $this->personelOlustur($firmaA, 'Firma A Personeli');
        $this->personelOlustur($firmaB, 'Firma B Personeli');

        $this->actingAs($this->userOlustur(['super_admin_mi' => true]));

        $this->assertEqualsCanonicalizing(
            ['Firma A Personeli', 'Firma B Personeli'],
            Personel::query()->pluck('ad_soyad')->all()
        );
    }

    public function test_personel_takip_sidebar_modul_ve_yetki_ile_gorunur(): void
    {
        $firma = $this->firmaOlustur('Firma A', 'firma-a');
        $user = $this->userOlustur();
        $rol = Rol::query()->create([
            'ad' => 'Personel Yoneticisi',
            'kod' => 'personel_yoneticisi',
            'sistem_rolu_mu' => false,
        ]);
        $yetki = Yetki::query()->create([
            'ad' => 'Personel Goruntule',
            'kod' => 'personel.goruntule',
            'modul_kodu' => 'personel_takip',
            'eylem' => 'goruntule',
        ]);
        $rol->yetkiler()->attach($yetki->id);

        $firmaKullanici = FirmaKullanici::query()->create([
            'firma_id' => $firma->id,
            'kullanici_id' => $user->id,
            'rol_id' => $rol->id,
            'durum' => 'aktif',
            'onay_durumu' => 'onaylandi',
            'varsayilan_firma_mi' => true,
        ]);

        $this->actingAs($user);
        app(TenantContextService::class)->firmaAyarla($firma, $rol->id, $firmaKullanici->id);

        $sidebar = app(SidebarService::class);

        $this->assertFalse($sidebar->sidebarBolumGorunurMu($user, $firma->id, 'personel_takip'));

        $modul = Modul::query()->create([
            'ad' => 'Personel Takip',
            'kod' => 'personel_takip',
            'aktif_mi' => true,
            'siralama' => 70,
        ]);
        FirmaModulu::query()->create([
            'firma_id' => $firma->id,
            'modul_id' => $modul->id,
            'durum' => 'aktif',
            'baslangic_tarihi' => now()->toDateString(),
        ]);

        Cache::flush();
        $this->app->forgetInstance(ModulErisimService::class);
        $this->app->forgetInstance(SidebarService::class);
        $this->app->forgetInstance(YetkiService::class);

        $this->assertTrue(app(SidebarService::class)->sidebarBolumGorunurMu($user, $firma->id, 'personel_takip'));
    }

    public function test_personel_resource_yetkisi_baska_firma_kaydina_uygulanmaz(): void
    {
        $firmaA = $this->firmaOlustur('Firma A', 'firma-a');
        $firmaB = $this->firmaOlustur('Firma B', 'firma-b');
        $user = $this->userOlustur();
        $rol = Rol::query()->create([
            'ad' => 'Personel Tam Yetkili',
            'kod' => 'personel_tam_yetkili',
            'sistem_rolu_mu' => false,
        ]);

        foreach ([
            'personel.goruntule' => 'goruntule',
            'personel.guncelle' => 'guncelle',
            'personel.sil' => 'sil',
        ] as $kod => $eylem) {
            $yetki = Yetki::query()->create([
                'ad' => $kod,
                'kod' => $kod,
                'modul_kodu' => 'personel_takip',
                'eylem' => $eylem,
            ]);
            $rol->yetkiler()->attach($yetki->id);
        }

        $firmaKullanici = FirmaKullanici::query()->create([
            'firma_id' => $firmaA->id,
            'kullanici_id' => $user->id,
            'rol_id' => $rol->id,
            'durum' => 'aktif',
            'onay_durumu' => 'onaylandi',
            'varsayilan_firma_mi' => true,
        ]);
        $this->personelTakipModuluAc($firmaA);

        $firmaAPersoneli = $this->personelOlustur($firmaA, 'Firma A Personeli');
        $firmaBPersoneli = $this->personelOlustur($firmaB, 'Firma B Personeli');

        $this->actingAs($user);
        app(TenantContextService::class)->firmaAyarla($firmaA, $rol->id, $firmaKullanici->id);
        Cache::flush();
        $this->app->forgetInstance(ModulErisimService::class);
        $this->app->forgetInstance(SidebarService::class);
        $this->app->forgetInstance(YetkiService::class);

        $this->assertTrue(PersonelKaynagi::canView($firmaAPersoneli));
        $this->assertTrue(PersonelKaynagi::canEdit($firmaAPersoneli));
        $this->assertTrue(PersonelKaynagi::canDelete($firmaAPersoneli));

        $this->assertFalse(PersonelKaynagi::canView($firmaBPersoneli));
        $this->assertFalse(PersonelKaynagi::canEdit($firmaBPersoneli));
        $this->assertFalse(PersonelKaynagi::canDelete($firmaBPersoneli));
    }

    private function firmaOlustur(string $ad, string $kod): Firma
    {
        return Firma::query()->create([
            'ad' => $ad,
            'kisa_ad' => $ad,
            'firma_kodu' => $kod,
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $ekAlanlar
     */
    private function userOlustur(array $ekAlanlar = []): User
    {
        return User::query()->create(array_merge([
            'name' => 'Test Kullanici',
            'email' => uniqid('personel-test-', true).'@example.test',
            'password' => Hash::make('password'),
        ], $ekAlanlar));
    }

    private function personelOlustur(Firma $firma, string $adSoyad): Personel
    {
        return Personel::tenantScopeOlmadan(fn () => Personel::query()->create([
            'firma_id' => $firma->id,
            'ad_soyad' => $adSoyad,
            'calisma_tipi' => 'tam_zamanli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 0,
            'durum' => Personel::DURUM_AKTIF,
        ]));
    }

    private function personelTakipModuluAc(Firma $firma): void
    {
        $modul = Modul::query()->create([
            'ad' => 'Personel Takip',
            'kod' => 'personel_takip',
            'aktif_mi' => true,
            'siralama' => 70,
        ]);

        FirmaModulu::query()->create([
            'firma_id' => $firma->id,
            'modul_id' => $modul->id,
            'durum' => 'aktif',
            'baslangic_tarihi' => now()->toDateString(),
        ]);
    }
}
