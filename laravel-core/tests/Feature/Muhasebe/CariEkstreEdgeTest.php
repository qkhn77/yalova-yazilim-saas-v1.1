<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Servisler\CariEkstreServisi;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CariEkstreEdgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_donemde_hareket_yok_satir_ve_fifo_sifir(): void
    {
        $firma = Firma::query()->create([
            'ad' => 'F',
            'kisa_ad' => 'F',
            'firma_kodu' => 'F-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);

        $cari = Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'C-'.uniqid(),
            'ad' => 'C',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        $bas = Carbon::parse('2026-06-01')->startOfDay();
        $bit = Carbon::parse('2026-06-30')->endOfDay();

        $rapor = app(CariEkstreServisi::class)->ekstre(
            (int) $firma->id,
            (int) $cari->id,
            'TRY',
            $bas,
            $bit
        );

        $this->assertSame('0.00', $rapor['devreden']);
        $this->assertCount(0, $rapor['satirlar']);
        $this->assertSame('0.00', $rapor['guncel_bakiye']);
    }
}
