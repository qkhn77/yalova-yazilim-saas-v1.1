<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\CariHareketEslesmesi;
use App\Models\User;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariHareketBelgeTuru;
use App\Muhasebe\Enumlar\CariHareketDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Servisler\CariBakiyeServisi;
use App\Muhasebe\Servisler\CariHareketFifoEslestirmeServisi;
use App\Muhasebe\Servisler\CariHareketServisi;
use App\Services\TenantContextService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CariFifoAcikKalemTest extends TestCase
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

    public function test_kismi_odeme_fifo_eslesir(): void
    {
        [$firma, $cari] = $this->firmaVeKiraciOturumu('KF1');
        $svc = app(CariHareketServisi::class);
        $fifo = app(CariHareketFifoEslestirmeServisi::class);

        $fatura = $svc->kayitOlustur((int) $firma->id, [
            'cari_id' => (int) $cari->id,
            'belge_turu' => CariHareketBelgeTuru::Fatura,
            'belge_id' => 101,
            'islem_tarihi' => Carbon::parse('2026-01-10'),
            'vade_tarihi' => Carbon::parse('2026-02-10'),
            'borc' => '0',
            'alacak' => '100.00',
            'para_birimi' => 'TRY',
        ]);

        $tahsilat = $svc->kayitOlustur((int) $firma->id, [
            'cari_id' => (int) $cari->id,
            'belge_turu' => CariHareketBelgeTuru::Tahsilat,
            'belge_id' => 201,
            'islem_tarihi' => Carbon::parse('2026-01-15'),
            'borc' => '40.00',
            'alacak' => '0',
            'para_birimi' => 'TRY',
        ]);

        $this->assertSame(1, CariHareketEslesmesi::query()->count());
        $this->assertSame('60.00000000', $fifo->acikAlacakKapasitesi($fatura->fresh()));
        $this->assertSame('0.00000000', $fifo->acikBorcKapasitesi($tahsilat->fresh()));

        $farklar = app(CariBakiyeServisi::class)->fifoHamBakiyeFarklari((int) $firma->id, (int) $cari->id);
        $this->assertSame('0.00', $farklar['TRY'] ?? '0.00');
    }

    public function test_coklu_fatura_tek_tahsilat_fifo(): void
    {
        [$firma, $cari] = $this->firmaVeKiraciOturumu('KF2');
        $svc = app(CariHareketServisi::class);
        $fifo = app(CariHareketFifoEslestirmeServisi::class);

        $f1 = $svc->kayitOlustur((int) $firma->id, [
            'cari_id' => (int) $cari->id,
            'belge_turu' => CariHareketBelgeTuru::Fatura,
            'belge_id' => 1,
            'islem_tarihi' => Carbon::parse('2026-01-01'),
            'vade_tarihi' => null,
            'borc' => '0',
            'alacak' => '100.00',
            'para_birimi' => 'TRY',
        ]);
        $f2 = $svc->kayitOlustur((int) $firma->id, [
            'cari_id' => (int) $cari->id,
            'belge_turu' => CariHareketBelgeTuru::Fatura,
            'belge_id' => 2,
            'islem_tarihi' => Carbon::parse('2026-01-02'),
            'vade_tarihi' => null,
            'borc' => '0',
            'alacak' => '50.00',
            'para_birimi' => 'TRY',
        ]);

        $svc->kayitOlustur((int) $firma->id, [
            'cari_id' => (int) $cari->id,
            'belge_turu' => CariHareketBelgeTuru::Tahsilat,
            'belge_id' => 9,
            'islem_tarihi' => Carbon::parse('2026-01-05'),
            'borc' => '120.00',
            'alacak' => '0',
            'para_birimi' => 'TRY',
        ]);

        $this->assertSame('0.00000000', $fifo->acikAlacakKapasitesi($f1->fresh()));
        $this->assertSame('30.00000000', $fifo->acikAlacakKapasitesi($f2->fresh()));

        $farklar = app(CariBakiyeServisi::class)->fifoHamBakiyeFarklari((int) $firma->id, (int) $cari->id);
        $this->assertSame('0.00', $farklar['TRY'] ?? '0.00');
    }

    public function test_odeme_ile_gelen_fatura_borc_fifo(): void
    {
        [$firma, $cari] = $this->firmaVeKiraciOturumu('KF3');
        $svc = app(CariHareketServisi::class);
        $fifo = app(CariHareketFifoEslestirmeServisi::class);

        $fatura = $svc->kayitOlustur((int) $firma->id, [
            'cari_id' => (int) $cari->id,
            'belge_turu' => CariHareketBelgeTuru::Fatura,
            'belge_id' => 501,
            'islem_tarihi' => Carbon::parse('2026-03-01'),
            'vade_tarihi' => null,
            'borc' => '80.00',
            'alacak' => '0',
            'para_birimi' => 'TRY',
        ]);

        $odeme = $svc->kayitOlustur((int) $firma->id, [
            'cari_id' => (int) $cari->id,
            'belge_turu' => CariHareketBelgeTuru::Odeme,
            'belge_id' => 601,
            'islem_tarihi' => Carbon::parse('2026-03-05'),
            'borc' => '0',
            'alacak' => '80.00',
            'para_birimi' => 'TRY',
        ]);

        $this->assertSame(1, CariHareketEslesmesi::query()->count());
        $this->assertSame('0.00000000', $fifo->acikBorcKapasitesi($fatura->fresh()));
        $this->assertSame('0.00000000', $fifo->acikAlacakKapasitesi($odeme->fresh()));
    }

    public function test_satis_iadesi_borc_otomatik_tahsilat_eslesmez_fifo_tutarli(): void
    {
        [$firma, $cari] = $this->firmaVeKiraciOturumu('KF4');
        $svc = app(CariHareketServisi::class);

        $svc->kayitOlustur((int) $firma->id, [
            'cari_id' => (int) $cari->id,
            'belge_turu' => CariHareketBelgeTuru::Fatura,
            'belge_id' => 1,
            'islem_tarihi' => now(),
            'borc' => '25.00',
            'alacak' => '0',
            'para_birimi' => 'TRY',
        ]);

        $svc->kayitOlustur((int) $firma->id, [
            'cari_id' => (int) $cari->id,
            'belge_turu' => CariHareketBelgeTuru::Tahsilat,
            'belge_id' => 2,
            'islem_tarihi' => now(),
            'borc' => '10.00',
            'alacak' => '0',
            'para_birimi' => 'TRY',
        ]);

        $this->assertSame(0, CariHareketEslesmesi::query()->count());
        $farklar = app(CariBakiyeServisi::class)->fifoHamBakiyeFarklari((int) $firma->id, (int) $cari->id);
        $this->assertSame('0.00', $farklar['TRY'] ?? '0.00');
    }

    public function test_ters_kayit_eslesmeleri_kaldirilir_ve_fatura_acilir(): void
    {
        [$firma, $cari] = $this->firmaVeKiraciOturumu('KF5');
        $svc = app(CariHareketServisi::class);
        $fifo = app(CariHareketFifoEslestirmeServisi::class);

        $fatura = $svc->kayitOlustur((int) $firma->id, [
            'cari_id' => (int) $cari->id,
            'belge_turu' => CariHareketBelgeTuru::Fatura,
            'belge_id' => 1,
            'islem_tarihi' => now(),
            'borc' => '0',
            'alacak' => '100.00',
            'para_birimi' => 'TRY',
        ]);

        $tahsilat = $svc->kayitOlustur((int) $firma->id, [
            'cari_id' => (int) $cari->id,
            'belge_turu' => CariHareketBelgeTuru::Tahsilat,
            'belge_id' => 2,
            'islem_tarihi' => now(),
            'borc' => '40.00',
            'alacak' => '0',
            'para_birimi' => 'TRY',
        ]);

        $this->assertSame(1, CariHareketEslesmesi::query()->count());
        $this->assertSame('60.00000000', $fifo->acikAlacakKapasitesi($fatura->fresh()));

        $svc->tersKayitOlustur($tahsilat->fresh());

        $this->assertSame(0, CariHareketEslesmesi::query()->count());
        $this->assertSame(CariHareketDurumu::Iptal, $tahsilat->fresh()->durum);
        $this->assertSame('100.00000000', $fifo->acikAlacakKapasitesi($fatura->fresh()));

        $farklar = app(CariBakiyeServisi::class)->fifoHamBakiyeFarklari((int) $firma->id, (int) $cari->id);
        $this->assertSame('0.00', $farklar['TRY'] ?? '0.00');
    }
}
