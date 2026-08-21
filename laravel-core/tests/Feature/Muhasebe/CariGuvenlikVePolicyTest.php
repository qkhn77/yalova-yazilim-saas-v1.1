<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\User;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Policies\CariPolicy;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CariGuvenlikVePolicyTest extends TestCase
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

    public function test_tenant_global_scope_baska_firma_cari_bulunamaz(): void
    {
        $fa = $this->firmaOlustur('CARIA');
        $fb = $this->firmaOlustur('CARIB');

        $kullanici = User::query()->create([
            'name' => 'K',
            'email' => 'k-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => false,
        ]);

        $cariB = Cari::query()->create([
            'firma_id' => $fb->id,
            'kod' => 'C-B-'.uniqid(),
            'ad' => 'Cari B',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        $this->actingAs($kullanici);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $fa->id]);

        $this->assertNull(Cari::query()->find($cariB->id));
    }

    public function test_super_admin_scope_tum_firmalari_gorebilir(): void
    {
        $fa = $this->firmaOlustur('CSA');
        $fb = $this->firmaOlustur('CSB');
        $sa = User::query()->create([
            'name' => 'SA',
            'email' => 'sa-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => true,
        ]);

        $cariB = Cari::query()->create([
            'firma_id' => $fb->id,
            'kod' => 'C-SA-'.uniqid(),
            'ad' => 'Cari',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        $this->actingAs($sa);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $fa->id]);

        $this->assertNotNull(Cari::query()->find($cariB->id));
    }

    public function test_tenant_scope_olmadan_servis_erisimi(): void
    {
        $fb = $this->firmaOlustur('CTS');
        $cari = Cari::query()->create([
            'firma_id' => $fb->id,
            'kod' => 'CTS-'.uniqid(),
            'ad' => 'C',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        $bulunan = Cari::tenantScopeOlmadan(fn () => Cari::query()->whereKey($cari->id)->first());

        $this->assertNotNull($bulunan);
        $this->assertSame((int) $fb->id, (int) $bulunan->firma_id);
    }

    public function test_policy_super_admin_her_zaman_true(): void
    {
        $sa = User::query()->create([
            'name' => 'SA',
            'email' => 'sa2-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => true,
        ]);
        $f = $this->firmaOlustur('CPOL');
        $cari = Cari::query()->create([
            'firma_id' => $f->id,
            'kod' => 'CPOL-'.uniqid(),
            'ad' => 'C',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        $policy = app(CariPolicy::class);
        $this->assertTrue($policy->view($sa, $cari));
    }
}
