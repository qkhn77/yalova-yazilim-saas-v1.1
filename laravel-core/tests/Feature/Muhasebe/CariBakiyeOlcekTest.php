<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\CariHareketi;
use App\Models\Muhasebe\DovizKuru;
use App\Models\User;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariHareketBelgeTuru;
use App\Muhasebe\Enumlar\CariHareketDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Servisler\CariBakiyeServisi;
use App\Muhasebe\Servisler\CariEkstreServisi;
use App\Muhasebe\Servisler\CariHareketFifoEslestirmeServisi;
use App\Muhasebe\Servisler\CariHareketServisi;
use App\Muhasebe\Servisler\CariYaslandirmaServisi;
use App\Services\TenantContextService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Batch FIFO okumaları ile ölçek yolu; sonuçlar tekil sorgu ile aynı olmalı.
 */
class CariBakiyeOlcekTest extends TestCase
{
    use DatabaseTransactions;

    private function firmaVeKiraci(string $kod): array
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

        return [$firma, $cari];
    }

    public function test_cok_hareket_fifo_ham_acik_mutabakat(): void
    {
        [$firma, $cari] = $this->firmaVeKiraci('OL1');
        $svc = app(CariHareketServisi::class);

        $svc->kayitOlustur((int) $firma->id, [
            'cari_id' => (int) $cari->id,
            'belge_turu' => CariHareketBelgeTuru::Fatura,
            'belge_id' => 1,
            'islem_tarihi' => Carbon::parse('2026-01-01'),
            'vade_tarihi' => null,
            'borc' => '0',
            'alacak' => '500.00',
            'para_birimi' => 'TRY',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $svc->kayitOlustur((int) $firma->id, [
                'cari_id' => (int) $cari->id,
                'belge_turu' => CariHareketBelgeTuru::Tahsilat,
                'belge_id' => 100 + $i,
                'islem_tarihi' => Carbon::parse('2026-01-02')->addDays($i),
                'vade_tarihi' => null,
                'borc' => '100.00',
                'alacak' => '0',
                'para_birimi' => 'TRY',
            ]);
        }

        $bakiye = app(CariBakiyeServisi::class);
        foreach ($bakiye->fifoHamBakiyeFarklari((int) $firma->id, (int) $cari->id) as $fark) {
            $this->assertSame('0.00', $fark);
        }

        $acik = $bakiye->paraBirimiOzetleriAcikKalem((int) $firma->id, (int) $cari->id)->firstWhere('para_birimi', 'TRY');
        $this->assertNotNull($acik);
        $this->assertSame('0.00', $acik->bakiye);
    }

    public function test_toplu_eslesme_toplam_tekil_ile_aynı(): void
    {
        [$firma, $cari] = $this->firmaVeKiraci('OL2');
        $svc = app(CariHareketServisi::class);
        $fifo = app(CariHareketFifoEslestirmeServisi::class);

        $ids = [];
        $svc->kayitOlustur((int) $firma->id, [
            'cari_id' => (int) $cari->id,
            'belge_turu' => CariHareketBelgeTuru::Fatura,
            'belge_id' => 1,
            'islem_tarihi' => now(),
            'vade_tarihi' => null,
            'borc' => '0',
            'alacak' => '200.00',
            'para_birimi' => 'TRY',
        ]);
        for ($i = 0; $i < 3; $i++) {
            $t = $svc->kayitOlustur((int) $firma->id, [
                'cari_id' => (int) $cari->id,
                'belge_turu' => CariHareketBelgeTuru::Tahsilat,
                'belge_id' => 10 + $i,
                'islem_tarihi' => now(),
                'vade_tarihi' => null,
                'borc' => '50.00',
                'alacak' => '0',
                'para_birimi' => 'TRY',
            ]);
            $ids[] = (int) $t->getKey();
        }

        $top = $fifo->toplamEslesenToplamlariHareketBasina($ids);
        foreach ($ids as $hid) {
            $this->assertSame(
                $fifo->toplamEslesenBorcTarafindan($hid),
                $top['borc_taraf'][$hid] ?? '0.00'
            );
        }
    }

    public function test_coklu_para_birimi_acik_kalem_gruplari(): void
    {
        [$firma, $cari] = $this->firmaVeKiraci('OL3');
        $svc = app(CariHareketServisi::class);
        DovizKuru::query()->create([
            'firma_id' => $firma->id,
            'is_sabit' => false,
            'tanim_firma_kapsami' => $firma->id,
            'kaynak_para_birimi' => 'USD',
            'hedef_para_birimi' => 'TRY',
            'tarih' => now()->toDateString(),
            'kur' => '40',
            'manuel_mi' => true,
        ]);

        $svc->kayitOlustur((int) $firma->id, [
            'cari_id' => (int) $cari->id,
            'belge_turu' => CariHareketBelgeTuru::Fatura,
            'belge_id' => 1,
            'islem_tarihi' => now(),
            'vade_tarihi' => null,
            'borc' => '0',
            'alacak' => '10.00',
            'para_birimi' => 'TRY',
        ]);
        $svc->kayitOlustur((int) $firma->id, [
            'cari_id' => (int) $cari->id,
            'belge_turu' => CariHareketBelgeTuru::Fatura,
            'belge_id' => 2,
            'islem_tarihi' => now(),
            'vade_tarihi' => null,
            'borc' => '0',
            'alacak' => '20.00',
            'para_birimi' => 'USD',
        ]);

        $gruplar = app(CariBakiyeServisi::class)->paraBirimiOzetleriAcikKalem((int) $firma->id, (int) $cari->id);
        $kodlar = $gruplar->pluck('para_birimi')->sort()->values()->all();
        $this->assertSame(['TRY', 'USD'], $kodlar);
        $this->assertSame('-10.00', $gruplar->firstWhere('para_birimi', 'TRY')->bakiye);
        $this->assertSame('-20.00', $gruplar->firstWhere('para_birimi', 'USD')->bakiye);
    }

    public function test_yaslandirma_gecikme_kovasi_batch(): void
    {
        [$firma, $cari] = $this->firmaVeKiraci('OL4');
        Carbon::setTestNow(Carbon::parse('2026-06-15'));

        CariHareketi::query()->create([
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
            'belge_turu' => CariHareketBelgeTuru::Fatura->value,
            'belge_id' => 9,
            'islem_tarihi' => Carbon::parse('2026-05-01'),
            'vade_tarihi' => Carbon::parse('2026-05-10'),
            'borc' => '0',
            'alacak' => '50.00',
            'para_birimi' => 'TRY',
            'durum' => CariHareketDurumu::Aktif->value,
        ]);

        $satirlar = app(CariYaslandirmaServisi::class)->rapor((int) $firma->id, 'TRY');
        $satir = $satirlar->firstWhere('cari_id', (int) $cari->id);
        $this->assertNotNull($satir);
        $this->assertSame('-50.00', $satir['gun_30_60']);
        $this->assertSame('-50.00', $satir['guncel_bakiye']);

        Carbon::setTestNow();
    }

    public function test_ekstre_fifo_kolon_batch_ile(): void
    {
        [$firma, $cari] = $this->firmaVeKiraci('OL5');
        $svc = app(CariHareketServisi::class);

        $svc->kayitOlustur((int) $firma->id, [
            'cari_id' => (int) $cari->id,
            'belge_turu' => CariHareketBelgeTuru::Fatura,
            'belge_id' => 1,
            'islem_tarihi' => Carbon::parse('2026-03-01 10:00:00'),
            'vade_tarihi' => null,
            'borc' => '0',
            'alacak' => '100.00',
            'para_birimi' => 'TRY',
        ]);
        $svc->kayitOlustur((int) $firma->id, [
            'cari_id' => (int) $cari->id,
            'belge_turu' => CariHareketBelgeTuru::Tahsilat,
            'belge_id' => 2,
            'islem_tarihi' => Carbon::parse('2026-03-02 10:00:00'),
            'vade_tarihi' => null,
            'borc' => '40.00',
            'alacak' => '0',
            'para_birimi' => 'TRY',
        ]);

        $rapor = app(CariEkstreServisi::class)->ekstre(
            (int) $firma->id,
            (int) $cari->id,
            'TRY',
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-31')
        );

        $this->assertCount(2, $rapor['satirlar']);
        $tahsilSatir = $rapor['satirlar']->first(fn ($s) => $s['hareket']->belge_turu === CariHareketBelgeTuru::Tahsilat);
        $this->assertNotNull($tahsilSatir);
        $this->assertFalse($tahsilSatir['fifo_acik']);
        $this->assertSame('0.00', $tahsilSatir['kalan_tutar']);
    }
}
