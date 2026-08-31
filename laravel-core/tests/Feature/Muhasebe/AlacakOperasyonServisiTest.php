<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\AlacakPlanRevizyonu;
use App\Models\Muhasebe\AlacakPlanTaksiti;
use App\Models\Muhasebe\AlacakTahsilatEslesmesi;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\CariHareketi;
use App\Models\Muhasebe\DovizKuru;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\User;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Servisler\AlacakOperasyonServisi;
use App\Muhasebe\Servisler\AlacakPlanServisi;
use App\Muhasebe\Servisler\AlacakRaporServisi;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AlacakOperasyonServisiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_secili_vadeler_toplu_tahsil_edilir(): void
    {
        [$firma, $cari, $kasa] = $this->senaryoHazirla();
        $plan = $this->planOlustur($firma, $cari, '90.00', 3);
        $taksitler = AlacakPlanTaksiti::query()
            ->where('alacak_plan_id', $plan->id)
            ->orderBy('sira_no')
            ->get();

        $sonuc = app(AlacakOperasyonServisi::class)->topluTahsilatOlustur(
            $taksitler->take(2)->pluck('id')->all(),
            [
                'kanal' => 'kasa',
                'kasa_hesap_id' => $kasa->id,
                'tahsilat_tipi' => 'secili_kalan',
                'tarih' => now(),
                'aciklama' => 'Test toplu vade tahsilati',
            ]
        );

        $this->assertSame('60.00', (string) $sonuc['tahsil_edilen_tutar']);
        $this->assertSame(2, (int) $sonuc['islem_adedi']);
        $this->assertSame(2, (int) $sonuc['kapatilan_taksit_adedi']);
        $this->assertSame(2, AlacakTahsilatEslesmesi::query()->count());
        $this->assertSame('odendi', (string) $taksitler[0]->fresh()->durum);
        $this->assertSame('odendi', (string) $taksitler[1]->fresh()->durum);
        $this->assertSame('30.00', (string) $taksitler[2]->fresh()->kalan_tutar);
        $this->assertSame('30.00', (string) $plan->fresh()->kalan_tutar);
    }

    public function test_usd_vade_try_kasa_ile_guncel_kurdan_tahsil_edilir(): void
    {
        config([
            'muhasebe.coklu_para_birimi.aktif' => true,
            'muhasebe.coklu_para_birimi.kur_donusumu_aktif' => true,
            'muhasebe.coklu_para_birimi.baz_para_birimi' => 'TRY',
        ]);

        [$firma, $cari, $kasa] = $this->senaryoHazirla();
        foreach ([['2026-07-20', '40'], ['2026-08-01', '42']] as [$tarih, $kur]) {
            DovizKuru::query()->create([
                'firma_id' => $firma->id,
                'kaynak_para_birimi' => 'USD',
                'hedef_para_birimi' => 'TRY',
                'is_sabit' => false,
                'tanim_firma_kapsami' => $firma->id,
                'tarih' => $tarih,
                'kur' => $kur,
                'manuel_mi' => true,
            ]);
        }

        $plan = app(AlacakPlanServisi::class)->olustur($firma->id, [
            'cari_id' => $cari->id,
            'kaynak_turu' => 'manuel',
            'plan_turu' => 'veresiye',
            'toplam_tutar' => '100.00',
            'pesinat_tutari' => '0.00',
            'para_birimi' => 'USD',
            'baslangic_tarihi' => '2026-07-20',
            'ilk_vade_tarihi' => '2026-07-20',
            'taksit_sayisi' => 1,
            'taksit_araligi_gun' => 30,
            'aciklama' => 'USD vade dönüşüm testi',
            'olusturan_id' => auth()->id(),
        ]);

        $sonuc = app(AlacakOperasyonServisi::class)->topluTahsilatOlustur(
            [$plan->taksitler()->firstOrFail()->id],
            [
                'kanal' => 'kasa',
                'kasa_hesap_id' => $kasa->id,
                'tahsilat_tipi' => 'secili_kalan',
                'doviz_kuru' => '42',
                'hedef_tutar' => '4200.00',
                'tarih' => '2026-08-01 10:00:00',
                'aciklama' => 'USD vade TRY tahsilat',
            ]
        );

        $finans = \App\Models\Muhasebe\FinansHareketi::query()
            ->where('referans_turu', 'alacak_plan_taksiti')
            ->where('referans_id', $plan->taksitler()->firstOrFail()->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('100.00', (string) $sonuc['tahsil_edilen_tutar']);
        $this->assertSame('USD', (string) $finans->para_birimi);
        $this->assertSame('4200.00', (string) $finans->baz_tutar);
        $this->assertSame('TRY', (string) $finans->baz_para_birimi);
        $this->assertSame('42.00000000', (string) $finans->kur);
        $this->assertSame('odendi', (string) $plan->taksitler()->firstOrFail()->fresh()->durum);
    }

    public function test_usd_vade_try_kasa_ile_kursuz_tahsil_edilemez(): void
    {
        config([
            'muhasebe.coklu_para_birimi.aktif' => true,
            'muhasebe.coklu_para_birimi.kur_donusumu_aktif' => true,
            'muhasebe.coklu_para_birimi.baz_para_birimi' => 'TRY',
        ]);

        [$firma, $cari, $kasa] = $this->senaryoHazirla();
        DovizKuru::query()->create([
            'firma_id' => $firma->id,
            'kaynak_para_birimi' => 'USD',
            'hedef_para_birimi' => 'TRY',
            'is_sabit' => false,
            'tanim_firma_kapsami' => $firma->id,
            'tarih' => '2026-07-20',
            'kur' => '40',
            'manuel_mi' => true,
        ]);
        $plan = app(AlacakPlanServisi::class)->olustur($firma->id, [
            'cari_id' => $cari->id,
            'kaynak_turu' => 'manuel',
            'plan_turu' => 'veresiye',
            'toplam_tutar' => '100.00',
            'para_birimi' => 'USD',
            'baslangic_tarihi' => '2026-07-20',
            'ilk_vade_tarihi' => '2026-07-20',
            'taksit_sayisi' => 1,
            'taksit_araligi_gun' => 30,
            'olusturan_id' => auth()->id(),
        ]);

        $this->expectException(IsKuraliIstisnasi::class);
        app(AlacakOperasyonServisi::class)->topluTahsilatOlustur(
            [$plan->taksitler()->firstOrFail()->id],
            [
                'kanal' => 'kasa',
                'kasa_hesap_id' => $kasa->id,
                'tahsilat_tipi' => 'secili_kalan',
                'tarih' => '2026-08-01 10:00:00',
            ]
        );
    }

    public function test_plan_indirimle_kapatilir_ve_cari_mahsup_uretilir(): void
    {
        [$firma, $cari, $kasa] = $this->senaryoHazirla();
        $plan = $this->planOlustur($firma, $cari, '100.00', 2);

        $sonuc = app(AlacakOperasyonServisi::class)->planiKapat($plan, [
            'kanal' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
            'indirim_tutari' => '10.00',
            'tarih' => now(),
            'kapama_notu' => 'Test erken kapama indirimi',
            'olusturan_id' => auth()->id(),
        ]);

        $plan->refresh();
        $this->assertSame('10.00', (string) $sonuc['indirim_tutari']);
        $this->assertSame('90.00', (string) $sonuc['tahsilat']['tahsil_edilen_tutar']);
        $this->assertSame('odendi', (string) $plan->durum);
        $this->assertSame('0.00', (string) $plan->kalan_tutar);
        $this->assertSame('90.00', (string) $plan->toplam_tutar);
        $this->assertSame('90.00', (string) $plan->odenen_tutar);
        $this->assertSame(1, AlacakPlanRevizyonu::query()
            ->where('alacak_plan_id', $plan->id)
            ->where('revizyon_turu', 'erken_kapama_indirimi')
            ->count());

        $aktifMahsupBorcu = CariHareketi::query()
            ->where('firma_id', $firma->id)
            ->where('cari_id', $cari->id)
            ->where('belge_turu', 'mahsup')
            ->where('durum', 'aktif')
            ->sum('borc');
        $this->assertSame('10.00', number_format((float) $aktifMahsupBorcu, 2, '.', ''));

        $performans = app(AlacakRaporServisi::class)->tahsilatPerformansi($firma->id);
        $this->assertSame('90.00', (string) $performans[0]['tahsil_edilen_tutar']);
    }

    public function test_vade_farki_revizyonu_yeni_taksit_ve_cari_alacak_ekler(): void
    {
        [$firma, $cari] = $this->senaryoHazirla();
        $plan = $this->planOlustur($firma, $cari, '100.00', 2);

        app(AlacakPlanServisi::class)->planiRevizeEt($plan, [
            'revizyon_turu' => 'vade_farki_ekle',
            'vade_farki_tutari' => '15.00',
            'vade_farki_vade_tarihi' => now()->addDays(5)->toDateString(),
            'aciklama' => 'Test gecikme vade farki',
            'olusturan_id' => auth()->id(),
        ]);

        $plan->refresh();
        $this->assertSame('115.00', (string) $plan->toplam_tutar);
        $this->assertSame('115.00', (string) $plan->kalan_tutar);
        $this->assertSame('15.00', (string) $plan->vade_farki_tutari);
        $this->assertSame(3, AlacakPlanTaksiti::query()
            ->where('alacak_plan_id', $plan->id)
            ->whereNotIn('durum', ['iptal'])
            ->count());

        $aktifSatisAlacagi = CariHareketi::query()
            ->where('firma_id', $firma->id)
            ->where('cari_id', $cari->id)
            ->where('belge_turu', 'satis')
            ->where('durum', 'aktif')
            ->sum('alacak');
        $this->assertSame('115.00', number_format((float) $aktifSatisAlacagi, 2, '.', ''));
    }

    public function test_acik_veresiye_tutari_vadesi_ve_aciklamasi_duzenlenebilir(): void
    {
        [$firma, $cari] = $this->senaryoHazirla();
        $plan = $this->planOlustur($firma, $cari, '100.00', 1);
        $taksit = $plan->taksitler()->firstOrFail();
        $yeniVade = now()->addDays(45)->toDateString();

        app(AlacakPlanServisi::class)->planiRevizeEt($plan, [
            'revizyon_turu' => 'taksit_duzenle',
            'taksit_id' => $taksit->id,
            'yeni_tutar' => '125.00',
            'yeni_vade_tarihi' => $yeniVade,
            'plan_aciklama' => 'Musteri ile yeni vade konusunda anlasildi.',
            'aciklama' => 'Veresiye tutari ve vadesi guncellendi.',
            'olusturan_id' => auth()->id(),
        ]);

        $taksit->refresh();
        $plan->refresh();

        $this->assertSame('125.00', (string) $taksit->tutar);
        $this->assertSame('125.00', (string) $taksit->kalan_tutar);
        $this->assertSame($yeniVade, $taksit->vade_tarihi?->toDateString());
        $this->assertSame('125.00', (string) $plan->toplam_tutar);
        $this->assertSame('125.00', (string) $plan->kalan_tutar);
        $this->assertSame('Musteri ile yeni vade konusunda anlasildi.', (string) $plan->aciklama);
        $this->assertDatabaseHas('muhasebe_alacak_plan_revizyonlari', [
            'alacak_plan_id' => $plan->id,
            'revizyon_turu' => 'taksit_duzenle',
        ]);
    }

    /**
     * @return array{0:Firma,1:Cari,2:KasaHesabi}
     */
    private function senaryoHazirla(): array
    {
        $firma = Firma::query()->create([
            'ad' => 'Alacak Operasyon Test Firma',
            'kisa_ad' => 'AOT',
            'firma_kodu' => 'AOT-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);

        $user = User::query()->create([
            'name' => 'Alacak Operasyon User',
            'email' => 'alacak-operasyon-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => true,
        ]);
        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $cari = Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'AOT-CARI-'.uniqid(),
            'ad' => 'Alacak Operasyon Cari',
            'tur' => 'musteri',
            'durum' => 'aktif',
            'para_birimi' => 'TRY',
        ]);

        $kasa = KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'AOT-KASA',
            'ad' => 'Test Kasa',
            'para_birimi' => 'TRY',
            'durum' => HesapDurumu::Aktif,
        ]);

        return [$firma, $cari, $kasa];
    }

    private function planOlustur(Firma $firma, Cari $cari, string $tutar, int $taksitSayisi)
    {
        return app(AlacakPlanServisi::class)->olustur($firma->id, [
            'cari_id' => $cari->id,
            'kaynak_turu' => 'manuel',
            'plan_turu' => $taksitSayisi > 1 ? 'taksit' : 'veresiye',
            'toplam_tutar' => $tutar,
            'pesinat_tutari' => '0.00',
            'para_birimi' => 'TRY',
            'baslangic_tarihi' => now()->toDateString(),
            'ilk_vade_tarihi' => now()->addDays(10)->toDateString(),
            'taksit_sayisi' => $taksitSayisi,
            'taksit_araligi_gun' => 10,
            'aciklama' => 'Test alacak plani',
            'olusturan_id' => auth()->id(),
        ]);
    }
}
