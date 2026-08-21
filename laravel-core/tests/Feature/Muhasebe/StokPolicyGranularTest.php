<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\FirmaKullanici;
use App\Models\FirmaModulu;
use App\Models\Modul;
use App\Models\Muhasebe\StokKarti;
use App\Models\Rol;
use App\Models\User;
use App\Models\Yetki;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Policies\StokKartiPolicy;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StokPolicyGranularTest extends TestCase
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

    public function test_sadece_stok_guncelle_ise_view_any_ve_view_true(): void
    {
        $firma = $this->firmaVeMuhasebeModulu('SG1');
        $firmaB = $this->firmaVeMuhasebeModulu('SG1B');

        $yetki = Yetki::query()->create([
            'ad' => 'Stok güncelle',
            'kod' => MuhasebeYetkiSablonlari::STOK_GUNCELLE,
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

        $policy = app(StokKartiPolicy::class);
        $this->assertTrue($policy->viewAny($user));

        $stok = StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'S-'.uniqid(),
            'ad' => 'Stok',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);
        $this->assertTrue($policy->view($user, $stok));

        $stokB = StokKarti::query()->create([
            'firma_id' => $firmaB->id,
            'kod' => 'SB-'.uniqid(),
            'ad' => 'B',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);
        $this->assertFalse($policy->view($user, $stokB));
    }
}
