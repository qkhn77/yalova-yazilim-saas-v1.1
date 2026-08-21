<?php

namespace Tests\Feature;

use App\Models\Firma;
use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\CariGrubu;
use App\Models\Muhasebe\MuhasebeLogoTuru;
use App\Models\Muhasebe\MuhasebeMalzemeTuru;
use App\Models\Muhasebe\MuhasebeMarka;
use App\Models\Muhasebe\MuhasebeOdemeYontemi;
use App\Models\Muhasebe\MuhasebeStokModeli;
use App\Models\Muhasebe\MuhasebeTasarim;
use App\Models\Muhasebe\MuhasebeVaryant;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\VergiOrani;
use App\Models\User;
use App\Muhasebe\Tanimlar\MuhasebeTanimKayitMutator;
use App\Muhasebe\Tanimlar\TanimKullanimDenetleyicisi;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MuhasebeTanim151KapsamTest extends TestCase
{
    use RefreshDatabase;

    private function firmaOlustur(string $ad = 'F'): Firma
    {
        return Firma::query()->create([
            'ad' => $ad,
            'kisa_ad' => $ad,
            'firma_kodu' => 'T'.random_int(10000, 99999),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
    }

    public function test_standart_tanim_turlerinde_firma_izolasyonu(): void
    {
        $a = $this->firmaOlustur('A');
        $b = $this->firmaOlustur('B');

        $siniflar = [
            Birim::class,
            VergiOrani::class,
            CariGrubu::class,
            MuhasebeOdemeYontemi::class,
            MuhasebeMarka::class,
            MuhasebeTasarim::class,
            MuhasebeMalzemeTuru::class,
            MuhasebeLogoTuru::class,
            MuhasebeVaryant::class,
        ];

        foreach ($siniflar as $sinif) {
            $sinif::tenantScopeOlmadan(function () use ($sinif, $b): void {
                $sinif::query()->create([
                    'firma_id' => $b->id,
                    'kod' => 'X'.substr(md5($sinif), 0, 4),
                    'ad' => 'B tarafı',
                    'aktif_mi' => true,
                    'is_sabit' => false,
                    ...($sinif === VergiOrani::class ? ['oran' => 10.0] : []),
                ]);
            });
        }

        MuhasebeStokModeli::tenantScopeOlmadan(function () use ($b): void {
            $marka = MuhasebeMarka::query()->create([
                'firma_id' => $b->id,
                'kod' => 'MB',
                'ad' => 'MB',
                'aktif_mi' => true,
                'is_sabit' => false,
            ]);
            MuhasebeStokModeli::query()->create([
                'firma_id' => $b->id,
                'marka_id' => $marka->id,
                'kod' => 'MOD',
                'ad' => 'Mod',
                'aktif_mi' => true,
                'is_sabit' => false,
            ]);
        });

        $user = User::factory()->create(['super_admin_mi' => false]);
        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $a->id]);

        foreach ($siniflar as $sinif) {
            $this->assertSame(0, $sinif::query()->count(), $sinif);
        }
        $this->assertSame(0, MuhasebeStokModeli::query()->count());
    }

    public function test_birim_stokta_kullanimliyken_silinemez(): void
    {
        $firma = $this->firmaOlustur();
        $birim = Birim::tenantScopeOlmadan(fn () => Birim::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'KOLI',
            'ad' => 'Koli',
            'aktif_mi' => true,
            'is_sabit' => false,
        ]));

        StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'S1',
            'ad' => 'Stok',
            'tur' => 'ticari_mal',
            'birim' => 'KOLI',
            'para_birimi' => 'TRY',
            'kdv_orani' => 0,
            'durum' => 'aktif',
        ]);

        $this->actingAs(User::factory()->create(['super_admin_mi' => true]));
        $this->assertTrue(TanimKullanimDenetleyicisi::kullanimdaMi($birim));
        $this->expectException(ValidationException::class);
        $birim->delete();
    }

    public function test_vergi_orani_kullanimliyken_silinemez(): void
    {
        $firma = $this->firmaOlustur();
        $vergi = VergiOrani::tenantScopeOlmadan(fn () => VergiOrani::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'K18',
            'ad' => '%18',
            'oran' => 18.0,
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
            'kdv_orani' => 18,
            'durum' => 'aktif',
        ]);

        $this->actingAs(User::factory()->create(['super_admin_mi' => true]));
        $this->assertTrue(TanimKullanimDenetleyicisi::kullanimdaMi($vergi));
        $this->expectException(ValidationException::class);
        $vergi->delete();
    }

    public function test_cari_grubu_caride_kullanimliyken_silinemez(): void
    {
        $firma = $this->firmaOlustur();
        $grup = CariGrubu::tenantScopeOlmadan(fn () => CariGrubu::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'G1',
            'ad' => 'Grup',
            'aktif_mi' => true,
            'is_sabit' => false,
        ]));

        Cari::query()->create([
            'firma_id' => $firma->id,
            'cari_grubu_id' => $grup->id,
            'kod' => 'C1',
            'ad' => 'Cari',
            'tur' => 'musteri',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);

        $this->actingAs(User::factory()->create(['super_admin_mi' => true]));
        $this->assertTrue(TanimKullanimDenetleyicisi::kullanimdaMi($grup));
        $this->expectException(ValidationException::class);
        $grup->delete();
    }

    public function test_marka_alt_model_varken_silinemez(): void
    {
        $firma = $this->firmaOlustur();
        $marka = MuhasebeMarka::tenantScopeOlmadan(fn () => MuhasebeMarka::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'M1',
            'ad' => 'M',
            'aktif_mi' => true,
            'is_sabit' => false,
        ]));

        MuhasebeStokModeli::tenantScopeOlmadan(fn () => MuhasebeStokModeli::query()->create([
            'firma_id' => $firma->id,
            'marka_id' => $marka->id,
            'kod' => 'MD',
            'ad' => 'D',
            'aktif_mi' => true,
            'is_sabit' => false,
        ]));

        $this->actingAs(User::factory()->create(['super_admin_mi' => true]));
        $this->assertTrue(TanimKullanimDenetleyicisi::kullanimdaMi($marka));
        $this->expectException(ValidationException::class);
        $marka->delete();
    }

    public function test_super_admin_firma_adina_tanim_mutator_ile_olusturur(): void
    {
        $firma = $this->firmaOlustur();
        $this->actingAs(User::factory()->create(['super_admin_mi' => true]));

        $veri = MuhasebeTanimKayitMutator::olustur([
            'is_sabit' => false,
            'firma_id' => $firma->id,
            'kod' => 'MT',
            'ad' => 'Metre',
            'aktif_mi' => true,
        ], Birim::class);

        $this->assertFalse($veri['is_sabit']);
        $this->assertSame($firma->id, $veri['firma_id']);

        $kayit = Birim::query()->create($veri);
        $this->assertSame($firma->id, (int) $kayit->firma_id);
        $this->assertFalse((bool) $kayit->is_sabit);
    }

    public function test_hizli_eklemeyle_olusan_birim_firma_tanimidir(): void
    {
        $firma = $this->firmaOlustur();
        $this->actingAs(User::factory()->create(['super_admin_mi' => false]));
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $kayit = Birim::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'PK',
            'ad' => 'Paket',
            'aktif_mi' => true,
            'is_sabit' => false,
        ]);

        $this->assertFalse((bool) $kayit->is_sabit);
        $this->assertSame($firma->id, (int) $kayit->firma_id);
    }
}
