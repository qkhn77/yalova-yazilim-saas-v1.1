<?php

namespace Tests\Feature\Muhasebe;

use App\Muhasebe\Servisler\CanonicalBirimGecisServisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class CanonicalBirimGecisiTest extends TestCase
{
    use RefreshDatabase;

    public function test_adet_only_satiri_id_korunarak_ad_olur(): void
    {
        $this->yalnizAdetDurumu();

        $this->assertTrue(CanonicalBirimGecisServisi::adetKodunuAdYap());
        $this->assertDatabaseHas('muhasebe_birimler', ['id' => 1, 'kod' => 'AD', 'ad' => 'Adet']);
        $this->assertDatabaseMissing('muhasebe_birimler', ['kod' => 'ADET']);
    }

    public function test_fresh_migration_sonucu_yalniz_canonical_ad_vardir(): void
    {
        $this->assertSame(1, DB::table('muhasebe_birimler')->where('kod', 'AD')->count());
        $this->assertSame(0, DB::table('muhasebe_birimler')->where('kod', 'ADET')->count());
    }

    public function test_ikinci_calistirma_no_op_olur(): void
    {
        $this->assertFalse(CanonicalBirimGecisServisi::adetKodunuAdYap());
        $this->assertSame(1, DB::table('muhasebe_birimler')->where('kod', 'AD')->count());
    }

    public function test_ad_ve_adet_birlikteyse_otomatik_merge_yapmaz(): void
    {
        DB::table('muhasebe_birimler')->insert([
            'kod' => 'ADET', 'ad' => 'Adet', 'aktif_mi' => true,
            'tanim_firma_kapsami' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(CanonicalBirimGecisServisi::DUPLICATE_STATE);
        CanonicalBirimGecisServisi::adetKodunuAdYap();
    }

    private function yalnizAdetDurumu(): void
    {
        DB::table('muhasebe_birimler')->whereIn('kod', ['AD', 'ADET'])->delete();
        DB::table('muhasebe_birimler')->where('id', 1)->delete();
        DB::table('muhasebe_birimler')->insert([
            'id' => 1, 'kod' => 'ADET', 'ad' => 'Adet', 'aktif_mi' => true,
            'tanim_firma_kapsami' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
