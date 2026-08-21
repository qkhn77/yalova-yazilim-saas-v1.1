<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\AlacakHatirlatmaLogu;
use App\Models\Muhasebe\AlacakTakipNotu;
use App\Models\Muhasebe\Cari;
use App\Models\User;
use App\Muhasebe\Servisler\AlacakHatirlatmaGonderimServisi;
use App\Muhasebe\Servisler\AlacakPlanServisi;
use App\Muhasebe\Servisler\AlacakRaporServisi;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlacakHatirlatmaVeRiskTest extends TestCase
{
    use RefreshDatabase;

    public function test_hatirlatma_gonderim_logu_olusturulur_ve_gunluk_tekrar_atlanir(): void
    {
        [$firma, $cari] = $this->senaryoHazirla();
        $this->planOlustur($firma, $cari, '1200.00', now()->subDays(20)->toDateString(), 2);

        $servis = app(AlacakHatirlatmaGonderimServisi::class);
        $sonuc = $servis->gonderimleriOlustur($firma->id, 'whatsapp', 7, 10);

        $this->assertSame(1, (int) $sonuc['olusturulan']);
        $this->assertSame(0, (int) $sonuc['atlanan']);
        $log = AlacakHatirlatmaLogu::query()->firstOrFail();
        $this->assertSame($firma->id, (int) $log->firma_id);
        $this->assertSame($cari->id, (int) $log->cari_id);
        $this->assertSame('whatsapp', (string) $log->kanal);
        $this->assertSame(AlacakHatirlatmaLogu::DURUM_KUYRUKTA, (string) $log->durum);
        $this->assertSame('905321234567', (string) $log->hedef);

        $tekrar = $servis->gonderimleriOlustur($firma->id, 'whatsapp', 7, 10);
        $this->assertSame(0, (int) $tekrar['olusturulan']);
        $this->assertSame(1, (int) $tekrar['atlanan']);
    }

    public function test_webhook_ayari_yokken_gonderim_kuyrukta_kalir(): void
    {
        [$firma, $cari] = $this->senaryoHazirla();
        $this->planOlustur($firma, $cari, '800.00', now()->subDays(5)->toDateString(), 1);

        $sonuc = app(AlacakHatirlatmaGonderimServisi::class)->gonderimleriOlustur(
            $firma->id,
            'sms',
            7,
            10,
            null,
            true,
            true,
        );

        $this->assertSame(1, (int) $sonuc['olusturulan']);
        $log = AlacakHatirlatmaLogu::query()->firstOrFail();
        $this->assertSame(AlacakHatirlatmaLogu::DURUM_KUYRUKTA, (string) $log->durum);
        $this->assertSame('kanal_entegrasyonu_yok', (string) $log->hata);
        $this->assertSame(1, (int) $log->deneme_sayisi);
    }

    public function test_risk_skoru_gecikme_ve_odeme_sozu_ihlaliyle_kritik_olur(): void
    {
        [$firma, $cari] = $this->senaryoHazirla();
        $plan = $this->planOlustur($firma, $cari, '6000.00', now()->subDays(45)->toDateString(), 3);

        AlacakTakipNotu::query()->create([
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
            'alacak_plan_id' => $plan->id,
            'takip_tipi' => 'arama',
            'durum' => 'odeme_sozu',
            'takip_tarihi' => now()->subDays(10),
            'sonraki_takip_tarihi' => now()->subDays(5),
            'odeme_sozu_tarihi' => now()->subDays(3),
            'odeme_sozu_tutari' => '1000.00',
            'odeme_sozu_durumu' => 'tutulmadi',
            'beklenen_tutar' => '1000.00',
            'para_birimi' => 'TRY',
            'not' => 'Test odeme sozu tutulmadi',
        ]);

        $satirlar = app(AlacakRaporServisi::class)->riskSkoruSatirlari($firma->id, 10);

        $this->assertCount(1, $satirlar);
        $this->assertSame($cari->id, (int) $satirlar[0]['cari_id']);
        $this->assertSame('kritik', (string) $satirlar[0]['risk_seviyesi']);
        $this->assertGreaterThanOrEqual(75, (int) $satirlar[0]['risk_skoru']);
        $this->assertSame(1, (int) $satirlar[0]['odeme_sozu_ihlali_adedi']);
    }

    /**
     * @return array{0:Firma,1:Cari}
     */
    private function senaryoHazirla(): array
    {
        $firma = Firma::query()->create([
            'ad' => 'Hatirlatma Risk Test Firma',
            'kisa_ad' => 'HRT',
            'firma_kodu' => 'HRT-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);

        $user = User::query()->create([
            'name' => 'Hatirlatma Risk User',
            'email' => 'hatirlatma-risk-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => true,
        ]);
        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $cari = Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'HRT-CARI-'.uniqid(),
            'ad' => 'Hatirlatma Risk Cari',
            'tur' => 'musteri',
            'durum' => 'aktif',
            'para_birimi' => 'TRY',
            'gsm' => '0532 123 45 67',
            'email' => 'risk-cari@test.local',
        ]);

        return [$firma, $cari];
    }

    private function planOlustur(Firma $firma, Cari $cari, string $tutar, string $ilkVade, int $taksitSayisi)
    {
        return app(AlacakPlanServisi::class)->olustur($firma->id, [
            'cari_id' => $cari->id,
            'kaynak_turu' => 'manuel',
            'plan_turu' => $taksitSayisi > 1 ? 'taksit' : 'veresiye',
            'toplam_tutar' => $tutar,
            'pesinat_tutari' => '0.00',
            'para_birimi' => 'TRY',
            'baslangic_tarihi' => now()->subDays(60)->toDateString(),
            'ilk_vade_tarihi' => $ilkVade,
            'taksit_sayisi' => $taksitSayisi,
            'taksit_araligi_gun' => 10,
            'aciklama' => 'Hatirlatma risk test plani',
            'olusturan_id' => auth()->id(),
        ]);
    }
}
