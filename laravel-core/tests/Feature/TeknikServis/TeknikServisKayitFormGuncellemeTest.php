<?php

namespace Tests\Feature\TeknikServis;

use App\Filament\Clusters\TeknikServis\Concerns\TeknikServisKayitFormSchema;
use App\Filament\Clusters\TeknikServis\Pages\TeslimFisi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages\TeknikServisKaydiDuzenle;
use App\Livewire\TeknikServis\YapilanTahsilatlarTablosu;
use App\Models\Firma;
use App\Models\Muhasebe\AlacakPlani;
use App\Models\Muhasebe\AlacakPlanTaksiti;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\VergiOrani;
use App\Models\TeknikServis\TeknikServisBaskiSablonu;
use App\Models\TeknikServis\TeknikServisDurumTanimi;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Models\TeknikServis\TeknikServisMuhasebeBaglantisi;
use App\Models\TeknikServis\TeknikServisTahsilati;
use App\Models\User;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Services\TenantContextService;
use App\TeknikServis\Enumlar\ServisTipi;
use App\TeknikServis\Servisler\TeknikServisTahsilatServisi;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\FileUpload;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use ReflectionMethod;
use Tests\TestCase;

class TeknikServisKayitFormGuncellemeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cihaz_gorseller_alani_append_ve_tekli_parallel_upload_ayari_kullanir(): void
    {
        $alan = $this->formBileseniBul(
            TeknikServisKayitFormSchema::bilesenler(true, ServisTipi::ArizaliCihaz),
            'cihaz_gorseller'
        );

        $this->assertInstanceOf(FileUpload::class, $alan);
        $this->assertTrue($alan->shouldAppendFiles());
        $this->assertSame(1, $alan->getMaxParallelUploads());
    }

    public function test_livewire_imzali_gorsel_urlsi_alt_dizin_normallestirilse_bile_gecerli_kalir(): void
    {
        URL::forceRootUrl('http://localhost/alt-dizin');

        try {
            $signedUrl = URL::temporarySignedRoute(
                'livewire.preview-file',
                now()->addMinutes(30),
                ['filename' => 'servis-ornek.png']
            );

            $path = (string) (parse_url($signedUrl, PHP_URL_PATH) ?? '');
            $query = (string) (parse_url($signedUrl, PHP_URL_QUERY) ?? '');
            $internalPath = preg_replace('#^/alt-dizin#', '', $path) ?: $path;

            $request = \Illuminate\Http\Request::create(
                'http://localhost' . $internalPath . ($query !== '' ? '?' . $query : ''),
                'GET'
            );
            $request->server->set('ORIG_REQUEST_URI', $path . ($query !== '' ? '?' . $query : ''));

            $this->assertFalse(URL::hasValidSignature($request));
            $this->assertTrue($request->hasValidSignature());
        } finally {
            URL::forceRootUrl(null);
        }
    }

    public function test_stok_ve_kdv_secenekleri_firma_baglamindan_uretilir(): void
    {
        $firmaA = $this->firmaOlustur('tsa');
        $firmaB = $this->firmaOlustur('tsb');

        StokKarti::query()->create([
            'firma_id' => $firmaA->id,
            'kod' => 'STK-A',
            'ad' => 'Ana Stok',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        StokKarti::query()->create([
            'firma_id' => $firmaB->id,
            'kod' => 'STK-B',
            'ad' => 'Diger Stok',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        VergiOrani::query()->create([
            'firma_id' => null,
            'kod' => 'KDV0',
            'ad' => 'KDV %0',
            'oran' => 0,
            'aktif_mi' => true,
            'is_sabit' => true,
        ]);

        VergiOrani::query()->create([
            'firma_id' => $firmaA->id,
            'kod' => 'KDV20',
            'ad' => 'KDV %20',
            'oran' => 20,
            'aktif_mi' => true,
            'is_sabit' => false,
        ]);

        VergiOrani::query()->create([
            'firma_id' => $firmaB->id,
            'kod' => 'KDV18',
            'ad' => 'KDV %18',
            'oran' => 18,
            'aktif_mi' => true,
            'is_sabit' => false,
        ]);

        $stokSecenekleri = $this->ozelStatikMetotCagir('stokSecenekleri', $firmaA->id);
        $vergiSecenekleri = $this->ozelStatikMetotCagir('vergiOraniSecenekleri', $firmaA->id);

        $this->assertTrue(collect($stokSecenekleri)->contains(fn (string $etiket): bool => str_contains($etiket, 'Ana Stok')));
        $this->assertFalse(collect($stokSecenekleri)->contains(fn (string $etiket): bool => str_contains($etiket, 'Diger Stok')));
        $this->assertSame('%0', $vergiSecenekleri['0'] ?? null);
        $this->assertSame('%20', $vergiSecenekleri['20'] ?? null);
        $this->assertArrayNotHasKey('18', $vergiSecenekleri);
    }

    public function test_teknik_servis_kaydi_yapilan_islemler_alanini_destekler(): void
    {
        $kayit = new TeknikServisKaydi([
            'yapilan_islemler' => 'Termal bakım yapıldı.',
        ]);

        $this->assertSame('Termal bakım yapıldı.', $kayit->yapilan_islemler);
    }

    public function test_create_ve_edit_form_semasi_muhasebe_bolumunu_kullanir(): void
    {
        $olusturmaBilesenleri = TeknikServisKayitFormSchema::bilesenler(true, ServisTipi::ArizaliCihaz);
        $duzenlemeBilesenleri = TeknikServisKayitFormSchema::bilesenler(false, null);

        $olusturmaBasliklari = $this->ustSeviyeBasliklari($olusturmaBilesenleri);
        $duzenlemeBasliklari = $this->ustSeviyeBasliklari($duzenlemeBilesenleri);

        $this->assertContains('Muhasebe Kayıtları', $olusturmaBasliklari);
        $this->assertContains('Muhasebe Kayıtları', $duzenlemeBasliklari);
        $this->assertNotContains('Tahsilat bilgileri', $olusturmaBasliklari);
        $this->assertNotContains('Tahsilat bilgileri', $duzenlemeBasliklari);
        $this->assertNull($this->formBileseniBul($duzenlemeBilesenleri, 'bagli_bekleyen_fatura_bilgisi'));
        $this->assertNotNull($this->formBileseniBul($duzenlemeBilesenleri, 'yapilan_islemler'));
    }

    public function test_duzenleme_kaydetme_sonrasi_tam_sayfa_yonlendirme_yapmaz(): void
    {
        $metot = new ReflectionMethod(TeknikServisKaydiDuzenle::class, 'getRedirectUrl');
        $metot->setAccessible(true);

        $this->assertNull($metot->invoke(new TeknikServisKaydiDuzenle()));
    }

    public function test_tahsilat_tablosu_fatura_sutununda_bagli_faturayi_gosterir(): void
    {
        $firma = $this->firmaOlustur('ts-muh');
        $kullanici = $this->superAdminOlustur();
        $cari = $this->cariOlustur($firma);
        $durum = $this->durumOlustur();
        $servis = $this->servisKaydiOlustur($firma, $cari, $durum, $kullanici);

        $fatura = Fatura::query()->create([
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
            'tur' => FaturaTuru::Giden->value,
            'durum' => FaturaDurumu::Onayli->value,
            'fatura_no' => '2026-000001',
            'tarih' => now(),
            'ara_toplam' => 1500,
            'kdv_toplam' => 0,
            'genel_toplam' => 1500,
            'odenecek_tutar' => 1500,
            'para_birimi' => 'TRY',
        ]);

        TeknikServisMuhasebeBaglantisi::query()->create([
            'firma_id' => $firma->id,
            'teknik_servis_kaydi_id' => $servis->id,
            'islem_tipi' => 'satis',
            'idempotency_key' => 'ts-test-fatura-'.$servis->id,
            'satis_faturasi_id' => $fatura->id,
            'senkron_durumu' => 'beklemede',
        ]);

        TeknikServisTahsilati::query()->create([
            'firma_id' => $firma->id,
            'teknik_servis_kaydi_id' => $servis->id,
            'satis_faturasi_id' => $fatura->id,
            'kanal' => 'kasa',
            'kaynak_para_birimi' => 'TRY',
            'hedef_para_birimi' => 'TRY',
            'tutar' => 1500,
            'hedef_tutar' => 1500,
            'tarih' => now(),
            'durum' => 'aktif',
            'olusturan_id' => $kullanici->id,
        ]);

        $this->actingAs($kullanici);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $bilesen = new YapilanTahsilatlarTablosu();
        $bilesen->record = $servis;

        $ozetHtml = (string) $this->ozelNesneMetotCagir($bilesen, 'tahsilatTablosuAciklamaMetni');
        $faturaHucreHtml = (string) $this->ozelNesneMetotCagir(
            $bilesen,
            'faturaEtiketi',
            TeknikServisTahsilati::query()->firstOrFail()
        );

        $this->assertStringContainsString('/finans-hareketleri', $ozetHtml);
        $this->assertStringContainsString('2026-000001', $ozetHtml);
        $this->assertStringContainsString('2026-000001', $faturaHucreHtml);
        $this->assertStringContainsString('Durum:', $faturaHucreHtml);
    }

    public function test_teknik_servis_tahsilat_modalindan_taksitli_plan_ve_pesinat_olusturulur(): void
    {
        $firma = $this->firmaOlustur('ts-vade');
        $kullanici = $this->superAdminOlustur();
        $cari = $this->cariOlustur($firma);
        $durum = $this->durumOlustur();
        $servis = $this->servisKaydiOlustur($firma, $cari, $durum, $kullanici);
        $kasa = KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'KASA-'.uniqid(),
            'ad' => 'Ana Kasa',
            'para_birimi' => 'TRY',
            'durum' => HesapDurumu::Aktif->value,
        ]);

        $this->actingAs($kullanici);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $plan = app(TeknikServisTahsilatServisi::class)->olustur($servis->fresh(['cari']) ?? $servis, [
            'kanal' => 'taksitli',
            'toplam_tutar' => '1500.00',
            'pesinat_tutari' => '100.00',
            'pesinat_kanali' => 'kasa',
            'pesinat_kasa_hesap_id' => $kasa->id,
            'kaynak_para_birimi' => 'TRY',
            'hedef_para_birimi' => 'TRY',
            'vade_tarihi' => now()->addDays(30)->toDateString(),
            'taksit_sayisi' => 3,
            'taksit_araligi_gun' => 30,
            'vade_farki_uygula' => true,
            'vade_farki_tipi' => 'tek_seferlik',
            'vade_farki_orani' => '10',
            'tarih' => now()->format('Y-m-d H:i:s'),
        ]);

        $this->assertInstanceOf(AlacakPlani::class, $plan);
        $this->assertSame('teknik_servis', (string) $plan->kaynak_turu);
        $this->assertSame('taksit', (string) $plan->plan_turu);
        $this->assertSame('1640.00', number_format((float) $plan->toplam_tutar, 2, '.', ''));
        $this->assertSame('100.00', number_format((float) $plan->pesinat_tutari, 2, '.', ''));
        $this->assertSame('1540.00', number_format((float) $plan->kalan_tutar, 2, '.', ''));
        $this->assertSame(3, AlacakPlanTaksiti::query()->where('alacak_plan_id', $plan->id)->count());

        $this->assertDatabaseHas('teknik_servis_tahsilatlari', [
            'firma_id' => $firma->id,
            'teknik_servis_kaydi_id' => $servis->id,
            'kanal' => 'kasa',
            'tutar' => '100.00',
            'durum' => 'aktif',
        ]);
    }

    public function test_farkli_para_birimli_teknik_servis_tahsilati_iptal_ve_duzeltmede_baz_kuru_korur(): void
    {
        $firma = $this->firmaOlustur('ts-doviz');
        $kullanici = $this->superAdminOlustur();
        $cari = $this->cariOlustur($firma, 'USD');
        $durum = $this->durumOlustur();
        $servis = $this->servisKaydiOlustur($firma, $cari, $durum, $kullanici);
        $kasa = KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'KASA-'.uniqid(),
            'ad' => 'TRY Kasa',
            'para_birimi' => 'TRY',
            'durum' => HesapDurumu::Aktif->value,
        ]);

        $this->actingAs($kullanici);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $servisTahsilati = app(TeknikServisTahsilatServisi::class)->olustur($servis->fresh(['cari']) ?? $servis, [
            'kanal' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
            'kaynak_para_birimi' => 'USD',
            'hedef_para_birimi' => 'TRY',
            'doviz_kuru_turu' => 'manuel',
            'doviz_kuru' => '40',
            'tutar' => '100',
            'hedef_tutar' => '4000',
            'tarih' => '2026-08-22 10:00:00',
        ]);

        $eskiFinansId = (int) $servisTahsilati->finans_hareketi_id;
        $this->assertDatabaseHas('finans_hareketleri', [
            'id' => $eskiFinansId,
            'tutar' => '100.00',
            'para_birimi' => 'USD',
            'baz_tutar' => '4000.00',
            'baz_para_birimi' => 'TRY',
            'kur' => '40.00000000',
        ]);

        $duzeltilmis = app(TeknikServisTahsilatServisi::class)->guncelle($servisTahsilati->fresh(), [
            'kanal' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
            'kaynak_para_birimi' => 'USD',
            'hedef_para_birimi' => 'TRY',
            'doviz_kuru_turu' => 'manuel',
            'doviz_kuru' => '42',
            'tutar' => '120',
            'hedef_tutar' => '5040',
            'tarih' => '2026-08-22 11:00:00',
        ]);

        $duzeltmeTersId = (int) $duzeltilmis->iptal_finans_hareketi_id;
        $this->assertDatabaseHas('finans_hareketleri', [
            'id' => $duzeltmeTersId,
            'durum' => 'aktif',
            'tutar' => '100.00',
            'baz_tutar' => '4000.00',
            'baz_para_birimi' => 'TRY',
            'kur' => '40.00000000',
        ]);
        $this->assertDatabaseHas('finans_hareketleri', [
            'id' => $eskiFinansId,
            'durum' => 'iptal',
        ]);
        $this->assertDatabaseHas('finans_hareketleri', [
            'id' => $duzeltilmis->finans_hareketi_id,
            'durum' => 'aktif',
            'tutar' => '120.00',
            'baz_tutar' => '5040.00',
            'baz_para_birimi' => 'TRY',
            'kur' => '42.00000000',
        ]);

        $iptalEdilmis = app(TeknikServisTahsilatServisi::class)->iptalEt($duzeltilmis->fresh(), 'Test iptali');
        $sonTersId = (int) $iptalEdilmis->iptal_finans_hareketi_id;
        $this->assertDatabaseHas('finans_hareketleri', [
            'id' => $sonTersId,
            'durum' => 'aktif',
            'tutar' => '120.00',
            'baz_tutar' => '5040.00',
            'baz_para_birimi' => 'TRY',
            'kur' => '42.00000000',
        ]);
        $this->assertDatabaseHas('finans_hareketleri', [
            'id' => $duzeltilmis->finans_hareketi_id,
            'durum' => 'iptal',
        ]);
    }

    public function test_teslim_fisi_varsayilan_teslim_sablonunu_kullanir(): void
    {
        $firma = $this->firmaOlustur('ts-teslim');
        $kullanici = $this->superAdminOlustur();
        $cari = $this->cariOlustur($firma);
        $durum = $this->durumOlustur();
        $servis = $this->servisKaydiOlustur($firma, $cari, $durum, $kullanici);

        TeknikServisBaskiSablonu::query()->create([
            'firma_id' => $firma->id,
            'sablon_turu' => 'teslim_belgesi',
            'ad' => 'Pasif Teslim',
            'kod' => 'teslim-test-pasif',
            'sayfa_tipi' => 'a4',
            'sablon_html' => '<div>PASIF {{SERVIS_NO}}</div>',
            'sablon_css' => '.pasif { color: #000; }',
            'varsayilan_mi' => false,
            'aktif' => true,
        ]);

        TeknikServisBaskiSablonu::query()->create([
            'firma_id' => $firma->id,
            'sablon_turu' => 'teslim_belgesi',
            'ad' => 'Varsayilan Teslim',
            'kod' => 'teslim-test-varsayilan',
            'sayfa_tipi' => 'a5',
            'sablon_html' => '<div>VARSAYILAN {{SERVIS_NO}} {{MUSTERI_AD}}</div>',
            'sablon_css' => '.varsayilan { color: #111; }',
            'varsayilan_mi' => true,
            'aktif' => true,
        ]);

        $this->actingAs($kullanici);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);
        request()->merge([
            'record' => $servis->id,
            'auto_print' => 1,
        ]);

        $sayfa = app(TeslimFisi::class);
        $sayfa->mount();

        $icerik = (string) $sayfa->icerik();

        $this->assertNotNull($sayfa->sablon);
        $this->assertSame('teslim-test-varsayilan', $sayfa->sablon?->kod);
        $this->assertStringContainsString('VARSAYILAN', $icerik);
        $this->assertStringNotContainsString('PASIF', $icerik);
        $this->assertStringContainsString((string) $servis->fis_no, $icerik);
        $this->assertStringContainsString('A5', strtoupper($sayfa->sayfaCss()));
    }

    private function superAdminOlustur(): User
    {
        return User::query()->create([
            'name' => 'Servis Admin',
            'email' => 'servis-admin-'.uniqid().'@test.local',
            'password' => bcrypt('password'),
            'super_admin_mi' => true,
        ]);
    }

    private function firmaOlustur(string $kod): Firma
    {
        return Firma::query()->create([
            'ad' => 'Firma '.$kod,
            'kisa_ad' => strtoupper(substr($kod, 0, 8)),
            'firma_kodu' => strtoupper($kod).'-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
    }

    private function cariOlustur(Firma $firma, string $paraBirimi = 'TRY'): Cari
    {
        return Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'CR-'.uniqid(),
            'ad' => 'Test Cari',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => $paraBirimi,
        ]);
    }

    private function durumOlustur(): TeknikServisDurumTanimi
    {
        return TeknikServisDurumTanimi::query()->create([
            'firma_id' => null,
            'ad' => 'Açık',
            'kod' => 'acik',
            'aktif' => true,
            'siralama' => 1,
            'varsayilan_mi' => false,
            'is_fiyat_verildi' => false,
            'is_teslim_edildi' => false,
            'is_iptal' => false,
            'is_iade' => false,
        ]);
    }

    private function servisKaydiOlustur(
        Firma $firma,
        Cari $cari,
        TeknikServisDurumTanimi $durum,
        User $kullanici,
        ?string $neden = null
    ): TeknikServisKaydi {
        return TeknikServisKaydi::query()->create([
            'firma_id' => $firma->id,
            'servis_tipi' => ServisTipi::ArizaliCihaz->value,
            'oncelik' => 'normal',
            'servis_kanali' => 'magaza',
            'cari_id' => $cari->id,
            'musteri_sikayeti' => 'Test sikayeti',
            'kabul_tarihi' => now(),
            'fis_no' => 'YB-SER'.random_int(1000, 9999),
            'musteri_onay_durumu' => 'beklemede',
            'servis_durumu_id' => $durum->id,
            'toplam_tutar' => 1500,
            'odenen_tutar' => 0,
            'odeme_durumu' => 'odenmedi',
            'iptal_nedeni' => $neden,
            'iade_nedeni' => $neden,
            'olusturan_id' => $kullanici->id,
        ]);
    }

    /**
     * @param  array<int, Component>  $bilesenler
     */
    private function formBileseniBul(array $bilesenler, string $alan): ?Component
    {
        foreach ($bilesenler as $bilesen) {
            if (method_exists($bilesen, 'getName') && $bilesen->getName() === $alan) {
                return $bilesen;
            }

            if (method_exists($bilesen, 'getChildComponents')) {
                $altBilesen = $this->formBileseniBul($bilesen->getChildComponents(), $alan);

                if ($altBilesen instanceof Component) {
                    return $altBilesen;
                }
            }
        }

        return null;
    }

    private function ozelStatikMetotCagir(string $metot, mixed ...$argumanlar): mixed
    {
        $yansima = new ReflectionMethod(TeknikServisKayitFormSchema::class, $metot);
        $yansima->setAccessible(true);

        return $yansima->invoke(null, ...$argumanlar);
    }

    private function ozelNesneMetotCagir(object $nesne, string $metot, mixed ...$argumanlar): mixed
    {
        $yansima = new ReflectionMethod($nesne, $metot);
        $yansima->setAccessible(true);

        return $yansima->invoke($nesne, ...$argumanlar);
    }

    /**
     * @param  array<int, Component>  $bilesenler
     * @return array<int, string>
     */
    private function ustSeviyeBasliklari(array $bilesenler): array
    {
        $basliklar = [];

        foreach ($bilesenler as $bilesen) {
            if (! method_exists($bilesen, 'getHeading')) {
                continue;
            }

            $baslik = $bilesen->getHeading();

            if (is_string($baslik) && $baslik !== '') {
                $basliklar[] = $baslik;
            }
        }

        return $basliklar;
    }
}
