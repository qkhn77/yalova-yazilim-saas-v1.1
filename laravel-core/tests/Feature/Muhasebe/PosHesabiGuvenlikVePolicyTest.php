<?php

namespace Tests\Feature\Muhasebe;

use App\Filament\Clusters\Muhasebe\Resources\PosHesabiKaynagi;
use App\Models\Firma;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\PosHesabi;
use App\Models\User;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Policies\PosHesabiPolicy;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosHesabiGuvenlikVePolicyTest extends TestCase
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

    public function test_tenant_global_scope_baska_firma_pos_bulunamaz(): void
    {
        $fa = $this->firmaOlustur('SCOPEA');
        $fb = $this->firmaOlustur('SCOPEB');

        $kullanici = User::query()->create([
            'name' => 'K',
            'email' => 'k-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => false,
        ]);

        $posB = PosHesabi::query()->create([
            'firma_id' => $fb->id,
            'kod' => 'P-B-'.uniqid(),
            'ad' => 'POS B',
            'para_birimi' => 'TRY',
            'durum' => HesapDurumu::Aktif->value,
        ]);

        $this->actingAs($kullanici);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $fa->id]);

        $this->assertNull(PosHesabi::query()->find($posB->id));
    }

    public function test_super_admin_scope_tum_firmalari_gorebilir(): void
    {
        $fa = $this->firmaOlustur('SAA');
        $fb = $this->firmaOlustur('SAB');
        $sa = User::query()->create([
            'name' => 'SA',
            'email' => 'sa-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => true,
        ]);

        $posB = PosHesabi::query()->create([
            'firma_id' => $fb->id,
            'kod' => 'P-SA-'.uniqid(),
            'ad' => 'POS',
            'para_birimi' => 'TRY',
            'durum' => HesapDurumu::Aktif->value,
        ]);

        $this->actingAs($sa);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $fa->id]);

        $this->assertNotNull(PosHesabi::query()->find($posB->id));
    }

    public function test_tenant_scope_olmadan_servis_erisimi(): void
    {
        $fb = $this->firmaOlustur('TSO');
        $pos = PosHesabi::query()->create([
            'firma_id' => $fb->id,
            'kod' => 'TSO-'.uniqid(),
            'ad' => 'P',
            'para_birimi' => 'TRY',
            'durum' => HesapDurumu::Aktif->value,
        ]);

        $bulunan = PosHesabi::tenantScopeOlmadan(fn () => PosHesabi::query()->whereKey($pos->id)->first());

        $this->assertNotNull($bulunan);
        $this->assertSame((int) $fb->id, (int) $bulunan->firma_id);
    }

    public function test_banka_hesabi_baska_firmaya_ait_olamaz(): void
    {
        $fa = $this->firmaOlustur('BANKA');
        $fb = $this->firmaOlustur('BANKB');

        $bankaB = BankaHesabi::query()->create([
            'firma_id' => $fb->id,
            'kod' => 'BK',
            'ad' => 'Banka B',
            'para_birimi' => 'TRY',
            'durum' => HesapDurumu::Aktif->value,
        ]);

        $this->expectException(ValidationException::class);

        PosHesabiKaynagi::dogrulaBankaHesabiFirma((int) $fa->id, [
            'banka_hesabi_id' => $bankaB->id,
        ]);
    }

    public function test_varsayilan_pos_transaction_icinde_tek_kayit(): void
    {
        $f = $this->firmaOlustur('VAR');

        $a = PosHesabi::query()->create([
            'firma_id' => $f->id,
            'kod' => 'V1-'.uniqid(),
            'ad' => 'A',
            'para_birimi' => 'TRY',
            'durum' => HesapDurumu::Aktif->value,
            'varsayilan_mi' => true,
        ]);
        $b = PosHesabi::query()->create([
            'firma_id' => $f->id,
            'kod' => 'V2-'.uniqid(),
            'ad' => 'B',
            'para_birimi' => 'TRY',
            'durum' => HesapDurumu::Aktif->value,
            'varsayilan_mi' => false,
        ]);

        $b->varsayilan_mi = true;
        $b->save();

        $this->assertTrue((bool) PosHesabi::query()->withoutGlobalScopes()->find($b->id)?->varsayilan_mi);
        $this->assertFalse((bool) PosHesabi::query()->withoutGlobalScopes()->find($a->id)?->varsayilan_mi);
        $this->assertSame(1, PosHesabi::query()->withoutGlobalScopes()->where('firma_id', $f->id)->where('varsayilan_mi', true)->count());
    }

    public function test_policy_super_admin_her_zaman_true(): void
    {
        $sa = User::query()->create([
            'name' => 'SA',
            'email' => 'sa2-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => true,
        ]);
        $f = $this->firmaOlustur('POL');
        $pos = PosHesabi::query()->create([
            'firma_id' => $f->id,
            'kod' => 'POL-'.uniqid(),
            'ad' => 'P',
            'para_birimi' => 'TRY',
            'durum' => HesapDurumu::Aktif->value,
        ]);

        $policy = app(PosHesabiPolicy::class);
        $this->assertTrue($policy->view($sa, $pos));
    }

    public function test_id_brute_force_baska_firma_kaydi_scope_disinda(): void
    {
        $fa = $this->firmaOlustur('BF1');
        $fb = $this->firmaOlustur('BF2');
        $u = User::query()->create([
            'name' => 'U',
            'email' => 'u-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => false,
        ]);
        $posFb = PosHesabi::query()->create([
            'firma_id' => $fb->id,
            'kod' => 'BF-'.uniqid(),
            'ad' => 'X',
            'para_birimi' => 'TRY',
            'durum' => HesapDurumu::Aktif->value,
        ]);

        $this->actingAs($u);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $fa->id]);

        $this->assertNull(PosHesabi::query()->whereKey($posFb->id)->first());
    }

    public function test_lock_for_update_ayni_firma_satirlari(): void
    {
        $f = $this->firmaOlustur('LOCK');
        PosHesabi::query()->create([
            'firma_id' => $f->id,
            'kod' => 'L1-'.uniqid(),
            'ad' => 'A',
            'para_birimi' => 'TRY',
            'durum' => HesapDurumu::Aktif->value,
        ]);

        DB::transaction(function () use ($f): void {
            PosHesabi::query()->where('firma_id', $f->id)->lockForUpdate()->get();
            $this->assertTrue(true);
        });
    }
}
