<?php

namespace Tests\Feature\Muhasebe;

use App\Filament\Clusters\Muhasebe\Pages\MuhasebeDashboardSayfasi;
use App\Models\Firma;
use App\Models\FirmaKullanici;
use App\Models\FirmaModulu;
use App\Models\Muhasebe\Fatura;
use App\Models\Modul;
use App\Models\Rol;
use App\Models\User;
use App\Models\Yetki;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MuhasebeDashboardUxTest extends TestCase
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

    public function test_muhasebe_dashboard_yetkili_kullanici_ve_firma_ile_acilir(): void
    {
        $firma = $this->firmaVeMuhasebeModulu('MDU1');

        $yetki = Yetki::query()->create([
            'ad' => 'Muhasebe görüntüle',
            'kod' => MuhasebeYetkiSablonlari::MUHASEBE_GORUNTULE,
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

        $this->assertTrue(MuhasebeDashboardSayfasi::canAccess());
    }

    public function test_dashboard_ozeti_acik_ve_vadesi_gecmis_adetlerini_ayri_doner(): void
    {
        $firma = $this->firmaVeMuhasebeModulu('MDU2');

        foreach ([
            ['vade_tarihi' => now()->subDay()->toDateString(), 'acik_tutar' => '100.00'],
            ['vade_tarihi' => now()->toDateString(), 'acik_tutar' => '50.00'],
        ] as $index => $veri) {
            Fatura::query()->create([
                'firma_id' => $firma->id,
                'tur' => FaturaTuru::Giden->value,
                'durum' => FaturaDurumu::Onayli->value,
                'fatura_no' => 'MDU2-'.$index,
                'tarih' => now(),
                'vade_tarihi' => $veri['vade_tarihi'],
                'ara_toplam' => $veri['acik_tutar'],
                'kdv_toplam' => '0.00',
                'genel_toplam' => $veri['acik_tutar'],
                'acik_tutar' => $veri['acik_tutar'],
                'odenecek_tutar' => $veri['acik_tutar'],
                'odendi_tutari' => '0.00',
                'genel_indirim_tutari' => '0.00',
                'toplam_indirim' => '0.00',
                'para_birimi' => 'TRY',
                'doviz_kuru' => '1.00000000',
            ]);
        }

        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $kpi = (new MuhasebeDashboardSayfasi())->ozet()['kpi'];

        $this->assertSame(2, $kpi['acik_fatura_sayisi']);
        $this->assertSame(1, $kpi['vadesi_gecmis_acik_sayisi']);
        $this->assertSame('100.00', $kpi['vadesi_gecmis_acik']);
    }
}
