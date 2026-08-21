<?php

namespace Tests\Feature;

use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\ParaBirimi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokKategorisi;
use App\Models\User;
use App\Muhasebe\Tanimlar\TanimKullanimDenetleyicisi;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MuhasebeTanimEnterpriseTest extends TestCase
{
    use RefreshDatabase;

    private function firmaOlustur(): Firma
    {
        return Firma::query()->create([
            'ad' => 'Test A.Ş.',
            'kisa_ad' => 'Test',
            'firma_kodu' => 'TST'.random_int(10000, 99999),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
    }

    public function test_firma_sadece_kendi_ve_sabit_para_birimini_gorur(): void
    {
        $firmaA = $this->firmaOlustur();
        $firmaB = $this->firmaOlustur();

        ParaBirimi::tenantScopeOlmadan(function () use ($firmaA, $firmaB): void {
            ParaBirimi::query()->create([
                'firma_id' => $firmaA->id,
                'kod' => 'EUR',
                'ad' => 'A Euro',
                'aktif_mi' => true,
                'is_sabit' => false,
            ]);
            ParaBirimi::query()->create([
                'firma_id' => $firmaB->id,
                'kod' => 'GBP',
                'ad' => 'B Pound',
                'aktif_mi' => true,
                'is_sabit' => false,
            ]);
        });

        $user = User::factory()->create(['super_admin_mi' => false]);
        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firmaA->id]);

        $kodlar = ParaBirimi::query()->orderBy('kod')->pluck('kod')->all();
        $this->assertEqualsCanonicalizing(['EUR', 'TRY'], $kodlar);
    }

    public function test_firma_baskasinin_tanimini_goremez(): void
    {
        $firmaA = $this->firmaOlustur();
        $firmaB = $this->firmaOlustur();

        ParaBirimi::tenantScopeOlmadan(function () use ($firmaB): void {
            ParaBirimi::query()->create([
                'firma_id' => $firmaB->id,
                'kod' => 'JPY',
                'ad' => 'Yen',
                'aktif_mi' => true,
                'is_sabit' => false,
            ]);
        });

        $this->actingAs(User::factory()->create(['super_admin_mi' => false]));
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firmaA->id]);

        $this->assertFalse(ParaBirimi::query()->where('kod', 'JPY')->exists());
    }

    public function test_kullanilan_para_birimi_silinemez(): void
    {
        $firma = $this->firmaOlustur();

        $para = ParaBirimi::tenantScopeOlmadan(fn () => ParaBirimi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'USD',
            'ad' => 'Dolar',
            'aktif_mi' => true,
            'is_sabit' => false,
        ]));

        Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'C1',
            'ad' => 'Cari',
            'tur' => 'musteri',
            'para_birimi' => 'USD',
            'durum' => 'aktif',
        ]);

        $this->actingAs(User::factory()->create(['super_admin_mi' => true]));

        $this->assertTrue(TanimKullanimDenetleyicisi::kullanimdaMi($para));

        try {
            $para->delete();
            $this->fail('Beklenen ValidationException oluşmadı.');
        } catch (ValidationException $e) {
            $flat = $this->validationMesajlariniBirlestir($e);
            $this->assertStringContainsString('kullanılmaktadır', $flat);
        }
    }

    public function test_sabit_tanim_sadece_super_admin_silebilir(): void
    {
        $para = ParaBirimi::tenantScopeOlmadan(fn () => ParaBirimi::query()->create([
            'firma_id' => null,
            'kod' => 'XXX',
            'ad' => 'Test sabit',
            'aktif_mi' => true,
            'is_sabit' => true,
        ]));

        $this->actingAs(User::factory()->create(['super_admin_mi' => false]));
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $this->firmaOlustur()->id]);

        try {
            $para->delete();
            $this->fail('Beklenen ValidationException oluşmadı.');
        } catch (ValidationException $e) {
            $flat = $this->validationMesajlariniBirlestir($e);
            $this->assertStringContainsString('süper yönetici', $flat);
        }
    }

    public function test_stok_kategorisi_kullanimda_silinemez(): void
    {
        $firma = $this->firmaOlustur();

        $kat = StokKategorisi::tenantScopeOlmadan(fn () => StokKategorisi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'K1',
            'ad' => 'Kat',
            'aktif_mi' => true,
            'is_sabit' => false,
        ]));

        StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'S1',
            'ad' => 'Stok',
            'tur' => 'ticari_mal',
            'birim' => 'AD',
            'para_birimi' => 'TRY',
            'kdv_orani' => 0,
            'durum' => 'aktif',
            'kategori_id' => $kat->id,
        ]);

        $this->actingAs(User::factory()->create(['super_admin_mi' => true]));

        try {
            $kat->delete();
            $this->fail('Beklenen ValidationException oluşmadı.');
        } catch (ValidationException $e) {
            $flat = $this->validationMesajlariniBirlestir($e);
            $this->assertStringContainsString('kullanılmaktadır', $flat);
        }
    }

    private function validationMesajlariniBirlestir(ValidationException $e): string
    {
        $parca = [];
        foreach ($e->errors() as $satir) {
            foreach ($satir as $m) {
                $parca[] = (string) $m;
            }
        }

        return implode(' ', $parca);
    }
}
