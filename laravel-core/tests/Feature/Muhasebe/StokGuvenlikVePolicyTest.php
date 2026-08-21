<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\StokHareketi;
use App\Models\Muhasebe\StokKarti;
use App\Models\User;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokBelgeTuru;
use App\Muhasebe\Enumlar\StokHareketDurumu;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Policies\StokKartiPolicy;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StokGuvenlikVePolicyTest extends TestCase
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

    public function test_tenant_global_scope_baska_firma_stok_karti_bulunamaz(): void
    {
        $fa = $this->firmaOlustur('SKA');
        $fb = $this->firmaOlustur('SKB');

        $kullanici = User::query()->create([
            'name' => 'K',
            'email' => 'k-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => false,
        ]);

        $stokB = StokKarti::query()->create([
            'firma_id' => $fb->id,
            'kod' => 'S-B-'.uniqid(),
            'ad' => 'Stok B',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        $this->actingAs($kullanici);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $fa->id]);

        $this->assertNull(StokKarti::query()->find($stokB->id));
    }

    public function test_super_admin_scope_tum_firmalari_gorebilir(): void
    {
        $fa = $this->firmaOlustur('SKSA');
        $fb = $this->firmaOlustur('SKSB');
        $sa = User::query()->create([
            'name' => 'SA',
            'email' => 'sa-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => true,
        ]);

        $stokB = StokKarti::query()->create([
            'firma_id' => $fb->id,
            'kod' => 'S-SA-'.uniqid(),
            'ad' => 'Stok',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        $this->actingAs($sa);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $fa->id]);

        $this->assertNotNull(StokKarti::query()->find($stokB->id));
    }

    public function test_tenant_scope_olmadan_servis_erisimi(): void
    {
        $fb = $this->firmaOlustur('SKTS');
        $stok = StokKarti::query()->create([
            'firma_id' => $fb->id,
            'kod' => 'SKTS-'.uniqid(),
            'ad' => 'S',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        $bulunan = StokKarti::tenantScopeOlmadan(fn () => StokKarti::query()->whereKey($stok->id)->first());

        $this->assertNotNull($bulunan);
        $this->assertSame((int) $fb->id, (int) $bulunan->firma_id);
    }

    public function test_stok_hareketi_tenant_ayrimi(): void
    {
        $fa = $this->firmaOlustur('SH1');
        $fb = $this->firmaOlustur('SH2');
        $stokB = StokKarti::query()->create([
            'firma_id' => $fb->id,
            'kod' => 'SH-'.uniqid(),
            'ad' => 'S',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);
        $hareket = StokHareketi::query()->create([
            'firma_id' => $fb->id,
            'stok_id' => $stokB->id,
            'islem_turu' => StokHareketIslemTuru::Alis,
            'miktar' => '1',
            'birim_fiyat' => '0',
            'toplam' => '0',
            'belge_turu' => StokBelgeTuru::Fatura,
            'belge_id' => 1,
            'tarih' => now(),
            'durum' => StokHareketDurumu::Aktif,
        ]);

        $kullanici = User::query()->create([
            'name' => 'K',
            'email' => 'kh-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => false,
        ]);
        $this->actingAs($kullanici);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $fa->id]);

        $this->assertNull(StokHareketi::query()->find($hareket->id));
    }

    public function test_policy_super_admin_her_zaman_true(): void
    {
        $sa = User::query()->create([
            'name' => 'SA',
            'email' => 'sa2-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => true,
        ]);
        $f = $this->firmaOlustur('SKPOL');
        $stok = StokKarti::query()->create([
            'firma_id' => $f->id,
            'kod' => 'SKPOL-'.uniqid(),
            'ad' => 'S',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        $policy = app(StokKartiPolicy::class);
        $this->assertTrue($policy->view($sa, $stok));
    }
}
