<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\CariHareketEslesmesi;
use App\Models\Muhasebe\CariHareketi;
use App\Models\User;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariHareketBelgeTuru;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Servisler\CariBakiyeServisi;
use App\Muhasebe\Servisler\CariHareketFifoEslestirmeServisi;
use App\Muhasebe\Servisler\CariHareketServisi;
use App\Services\TenantContextService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Paralel süreç simülasyonu: PHPUnit tek iş parçacığında gerçek yarış yok;
 * burada üst transaction + ardışık FIFO çağrıları ile kilit yolunun kırılmadığı doğrulanır.
 * Gerçek eşzamanlılık için MySQL/InnoDB altında stres veya iki bağlantı ile lock testi önerilir.
 */
class CariFifoConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private function firmaVeKiraciOturumu(string $kod): array
    {
        $firma = Firma::query()->create([
            'ad' => 'Test '.$kod,
            'kisa_ad' => $kod,
            'firma_kodu' => $kod.'-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
        $kullanici = User::query()->create([
            'name' => 'U',
            'email' => 'u-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => false,
        ]);
        $this->actingAs($kullanici);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $cari = Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'K-'.uniqid(),
            'ad' => 'Cari',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        return [$firma, $cari, $kullanici];
    }

    public function test_dis_transaction_icinde_coklu_hareket_fifo_tutarli(): void
    {
        [$firma, $cari] = $this->firmaVeKiraciOturumu('KCC1');
        $svc = app(CariHareketServisi::class);
        $fifo = app(CariHareketFifoEslestirmeServisi::class);

        DB::transaction(function () use ($svc, $firma, $cari): void {
            $svc->kayitOlustur((int) $firma->id, [
                'cari_id' => (int) $cari->id,
                'belge_turu' => CariHareketBelgeTuru::Fatura,
                'belge_id' => 1,
                'islem_tarihi' => Carbon::parse('2026-01-01'),
                'vade_tarihi' => null,
                'borc' => '0',
                'alacak' => '100.00',
                'para_birimi' => 'TRY',
            ]);
            $svc->kayitOlustur((int) $firma->id, [
                'cari_id' => (int) $cari->id,
                'belge_turu' => CariHareketBelgeTuru::Tahsilat,
                'belge_id' => 2,
                'islem_tarihi' => Carbon::parse('2026-01-02'),
                'borc' => '30.00',
                'alacak' => '0',
                'para_birimi' => 'TRY',
            ]);
            $svc->kayitOlustur((int) $firma->id, [
                'cari_id' => (int) $cari->id,
                'belge_turu' => CariHareketBelgeTuru::Tahsilat,
                'belge_id' => 3,
                'islem_tarihi' => Carbon::parse('2026-01-03'),
                'borc' => '40.00',
                'alacak' => '0',
                'para_birimi' => 'TRY',
            ]);
        });

        $this->assertSame(2, CariHareketEslesmesi::query()->count());
        $farklar = app(CariBakiyeServisi::class)->fifoHamBakiyeFarklari((int) $firma->id, (int) $cari->id);
        $this->assertSame('0.00', $farklar['TRY'] ?? '0.00');

        $faturalar = CariHareketi::query()
            ->where('firma_id', $firma->id)
            ->where('cari_id', $cari->id)
            ->where('belge_turu', CariHareketBelgeTuru::Fatura)
            ->orderBy('id')
            ->get();
        $this->assertCount(1, $faturalar);
        $this->assertSame('30.00000000', $fifo->acikAlacakKapasitesi($faturalar->first()));
    }
}
