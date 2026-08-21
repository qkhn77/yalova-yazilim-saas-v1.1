<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\User;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Servisler\FaturaFinansKapamaServisi;
use App\Muhasebe\Servisler\FaturaIslemServisi;
use App\Muhasebe\Servisler\FaturaKapamaDogrulamaServisi;
use App\Muhasebe\Servisler\FinansHareketServisi;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaturaFinansKapamaTest extends TestCase
{
    use RefreshDatabase;

    private function firmaOlustur(string $kod): Firma
    {
        return Firma::query()->create([
            'ad' => 'Test '.$kod,
            'kisa_ad' => $kod,
            'firma_kodu' => $kod.'-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
    }

    private function superAdminVeSession(Firma $firma): void
    {
        $user = User::query()->create([
            'name' => 'SA',
            'email' => 'sa-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => true,
        ]);
        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);
    }

    private function cariOlustur(Firma $firma, CariTuru $tur = CariTuru::Musteri): Cari
    {
        return Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'C-'.uniqid(),
            'ad' => 'Cari',
            'tur' => $tur->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);
    }

    private function kasaOlustur(Firma $firma): KasaHesabi
    {
        return KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'K-'.uniqid(),
            'ad' => 'Kasa',
            'para_birimi' => 'TRY',
            'durum' => HesapDurumu::Aktif->value,
        ]);
    }

    private function onayliFaturaOlustur(Firma $firma, Cari $cari, FaturaTuru $tur): Fatura
    {
        $fatura = Fatura::query()->create([
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
            'tur' => $tur->value,
            'durum' => FaturaDurumu::Taslak->value,
            'tarih' => now(),
            'ara_toplam' => '100',
            'kdv_toplam' => '20',
            'genel_toplam' => '120',
            'genel_indirim_tutari' => '0',
            'toplam_indirim' => '0',
            'odenecek_tutar' => '120',
            'odendi_tutari' => '0',
            'acik_tutar' => '120',
            'odeme_durumu' => 'odenmedi',
            'para_birimi' => 'TRY',
            'doviz_kuru' => '1',
        ]);
        FaturaKalemi::query()->create([
            'firma_id' => $firma->id,
            'fatura_id' => $fatura->id,
            'satir_no' => 1,
            'kalem_tipi' => 'hizmet_kalemi',
            'hizmet_mi' => true,
            'miktar' => '1',
            'birim_fiyat' => '100',
            'kdv_orani' => '20',
            'net_tutar' => '100',
            'kdv_tutari' => '20',
            'toplam' => '120',
            'satir_toplami' => '100',
            'satir_genel_toplam' => '120',
            'para_birimi' => 'TRY',
        ]);
        app(FaturaIslemServisi::class)->faturayiOnayla($fatura);

        return $fatura->fresh();
    }

    public function test_kismi_ve_tam_tahsilat_acik_tutari_dogru_gunceller(): void
    {
        $firma = $this->firmaOlustur('FK1');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, CariTuru::Musteri);
        $kasa = $this->kasaOlustur($firma);
        $fatura = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::GidenFatura);
        $servis = app(FinansHareketServisi::class);

        $servis->tahsilatKasadanKaydet($firma->id, $cari->id, $kasa->id, '40', 'TRY', now(), 'kismi', 'fatura', $fatura->id);
        $this->assertSame('40.00', number_format((float) $fatura->fresh()->odendi_tutari, 2, '.', ''));
        $this->assertSame('80.00', number_format((float) $fatura->fresh()->acik_tutar, 2, '.', ''));
        $this->assertSame('kismi_odendi', $fatura->fresh()->odeme_durumu);

        $servis->tahsilatKasadanKaydet($firma->id, $cari->id, $kasa->id, '80', 'TRY', now(), 'tamamla', 'fatura', $fatura->id);
        $this->assertSame('120.00', number_format((float) $fatura->fresh()->odendi_tutari, 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $fatura->fresh()->acik_tutar, 2, '.', ''));
        $this->assertSame('odendi', $fatura->fresh()->odeme_durumu);
    }

    public function test_ayni_finans_hareketi_iki_kez_uygulanmaz(): void
    {
        $firma = $this->firmaOlustur('FK2');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, CariTuru::Musteri);
        $kasa = $this->kasaOlustur($firma);
        $fatura = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::GidenFatura);
        $servis = app(FinansHareketServisi::class);

        $result = $servis->tahsilatKasadanKaydet($firma->id, $cari->id, $kasa->id, '60', 'TRY', now(), 'ilk', 'fatura', $fatura->id);
        $finans = $result['finans'];
        app(FaturaFinansKapamaServisi::class)->finansHareketiniFaturayaUygula($finans);
        $this->assertSame('60.00', number_format((float) $fatura->fresh()->odendi_tutari, 2, '.', ''));
    }

    public function test_fazla_odeme_davranisi_net_ve_testli(): void
    {
        $firma = $this->firmaOlustur('FK3');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, CariTuru::Musteri);
        $kasa = $this->kasaOlustur($firma);
        $fatura = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::GidenFatura);

        $finans = app(FinansHareketServisi::class)->tahsilatKasadanKaydet($firma->id, $cari->id, $kasa->id, '130', 'TRY', now(), 'fazla', 'fatura', $fatura->id)['finans'];
        $this->assertSame('120.00', number_format((float) $fatura->fresh()->odendi_tutari, 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $fatura->fresh()->acik_tutar, 2, '.', ''));
        $this->assertSame('odendi', $fatura->fresh()->odeme_durumu);
        $this->assertSame('10.00', number_format((float) $finans->fresh()->avans_tutar, 2, '.', ''));
    }

    public function test_iptal_ve_iade_once_aktif_finans_kapama_varsa_engellenir(): void
    {
        $firma = $this->firmaOlustur('FK4');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, CariTuru::Musteri);
        $kasa = $this->kasaOlustur($firma);
        $fatura = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::GidenFatura);
        app(FinansHareketServisi::class)->tahsilatKasadanKaydet($firma->id, $cari->id, $kasa->id, '10', 'TRY', now(), 'kapama', 'fatura', $fatura->id);

        $this->expectException(IsKuraliIstisnasi::class);
        app(FaturaIslemServisi::class)->faturayiIptalEt($fatura->fresh());
    }

    public function test_proforma_ve_bekleyen_finansal_kapamaya_girmez(): void
    {
        $firma = $this->firmaOlustur('FK5');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, CariTuru::Musteri);
        $kasa = $this->kasaOlustur($firma);

        $proforma = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::ProformaFatura);
        $this->expectException(IsKuraliIstisnasi::class);
        app(FinansHareketServisi::class)->tahsilatKasadanKaydet($firma->id, $cari->id, $kasa->id, '20', 'TRY', now(), 'proforma', 'fatura', $proforma->id);
    }

    public function test_bekleyen_fatura_finansal_kapamaya_girmez(): void
    {
        $firma = $this->firmaOlustur('FK5B');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, CariTuru::Musteri);
        $kasa = $this->kasaOlustur($firma);
        $bekleyen = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::BekleyenFatura);

        $this->expectException(IsKuraliIstisnasi::class);
        app(FinansHareketServisi::class)->tahsilatKasadanKaydet($firma->id, $cari->id, $kasa->id, '20', 'TRY', now(), 'bekleyen', 'fatura', $bekleyen->id);
    }

    public function test_alias_ve_kanonik_turler_ayni_odeme_sonucunu_uretir(): void
    {
        $firma = $this->firmaOlustur('FK6');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, CariTuru::Musteri);
        $kasa = $this->kasaOlustur($firma);
        $fAlias = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::GidenFatura);
        $fKanonik = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::Giden);

        app(FinansHareketServisi::class)->tahsilatKasadanKaydet($firma->id, $cari->id, $kasa->id, '50', 'TRY', now(), 'a', 'fatura', $fAlias->id);
        app(FinansHareketServisi::class)->tahsilatKasadanKaydet($firma->id, $cari->id, $kasa->id, '50', 'TRY', now(), 'k', 'fatura', $fKanonik->id);

        $this->assertSame($fAlias->fresh()->odeme_durumu, $fKanonik->fresh()->odeme_durumu);
        $this->assertSame(
            number_format((float) $fAlias->fresh()->acik_tutar, 2, '.', ''),
            number_format((float) $fKanonik->fresh()->acik_tutar, 2, '.', '')
        );
    }

    public function test_tek_finans_coklu_fatura_dagitimi_calisir(): void
    {
        config([
            'muhasebe.otomasyon.finans_otomatik_dagitim' => false,
            'muhasebe.otomasyon.avans_otomatik_mahsup' => false,
        ]);
        $firma = $this->firmaOlustur('FK7');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, CariTuru::Musteri);
        $kasa = $this->kasaOlustur($firma);
        $f1 = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::GidenFatura);
        $f2 = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::GidenFatura);
        $f3 = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::GidenFatura);
        $f1->update(['odenecek_tutar' => '400', 'acik_tutar' => '400', 'genel_toplam' => '400']);
        $f2->update(['odenecek_tutar' => '300', 'acik_tutar' => '300', 'genel_toplam' => '300']);
        $f3->update(['odenecek_tutar' => '300', 'acik_tutar' => '300', 'genel_toplam' => '300']);

        $finans = app(FinansHareketServisi::class)
            ->tahsilatKasadanKaydet($firma->id, $cari->id, $kasa->id, '1000', 'TRY', now(), 'dagitim')['finans'];

        app(FaturaFinansKapamaServisi::class)->finansiFaturalaraDagit($finans, [
            ['fatura_id' => $f1->id, 'tutar' => '400'],
            ['fatura_id' => $f2->id, 'tutar' => '300'],
            ['fatura_id' => $f3->id, 'tutar' => '300'],
        ]);

        $this->assertSame('1000.00', number_format((float) $finans->fresh()->kullanilan_tutar, 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $finans->fresh()->avans_tutar, 2, '.', ''));
    }

    public function test_toplam_asimi_engellenir(): void
    {
        $firma = $this->firmaOlustur('FK8');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, CariTuru::Musteri);
        $kasa = $this->kasaOlustur($firma);
        $f1 = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::GidenFatura);
        $f2 = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::GidenFatura);
        $finans = app(FinansHareketServisi::class)
            ->tahsilatKasadanKaydet($firma->id, $cari->id, $kasa->id, '100', 'TRY', now(), 'dagitim')['finans'];

        $this->expectException(IsKuraliIstisnasi::class);
        app(FaturaFinansKapamaServisi::class)->finansiFaturalaraDagit($finans, [
            ['fatura_id' => $f1->id, 'tutar' => '70'],
            ['fatura_id' => $f2->id, 'tutar' => '40'],
        ]);
    }

    public function test_duplicate_dagitim_engellenir(): void
    {
        $firma = $this->firmaOlustur('FK8B');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, CariTuru::Musteri);
        $kasa = $this->kasaOlustur($firma);
        $f1 = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::GidenFatura);
        $finans = app(FinansHareketServisi::class)
            ->tahsilatKasadanKaydet($firma->id, $cari->id, $kasa->id, '100', 'TRY', now(), 'dagitim')['finans'];

        $this->expectException(IsKuraliIstisnasi::class);
        app(FaturaFinansKapamaServisi::class)->finansiFaturalaraDagit($finans, [
            ['fatura_id' => $f1->id, 'tutar' => '50'],
            ['fatura_id' => $f1->id, 'tutar' => '50'],
        ]);
    }

    public function test_farkli_firma_finans_fatura_dagitimi_engellenir(): void
    {
        $f1 = $this->firmaOlustur('FK9A');
        $f2 = $this->firmaOlustur('FK9B');
        $this->superAdminVeSession($f1);
        $c1 = $this->cariOlustur($f1, CariTuru::Musteri);
        $k1 = $this->kasaOlustur($f1);
        $fatura1 = $this->onayliFaturaOlustur($f1, $c1, FaturaTuru::GidenFatura);
        $this->superAdminVeSession($f2);
        $c2 = $this->cariOlustur($f2, CariTuru::Musteri);
        $fatura2 = $this->onayliFaturaOlustur($f2, $c2, FaturaTuru::GidenFatura);
        $this->superAdminVeSession($f1);
        $finans = app(FinansHareketServisi::class)
            ->tahsilatKasadanKaydet($f1->id, $c1->id, $k1->id, '100', 'TRY', now(), 'dagitim')['finans'];

        $this->expectException(IsKuraliIstisnasi::class);
        app(FaturaFinansKapamaServisi::class)->finansiFaturalaraDagit($finans, [
            ['fatura_id' => $fatura1->id, 'tutar' => '50'],
            ['fatura_id' => $fatura2->id, 'tutar' => '50'],
        ]);
    }

    public function test_reconciliation_tutarsizligi_yakalar(): void
    {
        config(['muhasebe.fatura.kapama_tutarsizlik_hard_fail' => true]);
        $firma = $this->firmaOlustur('FK10');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, CariTuru::Musteri);
        $kasa = $this->kasaOlustur($firma);
        $fatura = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::GidenFatura);
        app(FinansHareketServisi::class)->tahsilatKasadanKaydet($firma->id, $cari->id, $kasa->id, '20', 'TRY', now(), 'a', 'fatura', $fatura->id);
        $fatura->update(['odendi_tutari' => '999']);

        $this->expectException(IsKuraliIstisnasi::class);
        app(FaturaKapamaDogrulamaServisi::class)->faturaKapamaDurumuDogrula($fatura->id);
    }

    public function test_faturaya_bagli_finans_hareketi_dogrudan_silinamaz(): void
    {
        $firma = $this->firmaOlustur('FK11');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, CariTuru::Musteri);
        $kasa = $this->kasaOlustur($firma);
        $fatura = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::GidenFatura);
        $finans = app(FinansHareketServisi::class)
            ->tahsilatKasadanKaydet($firma->id, $cari->id, $kasa->id, '10', 'TRY', now(), 'a', 'fatura', $fatura->id)['finans'];

        $this->expectException(\RuntimeException::class);
        $finans->delete();
    }

    public function test_onerilen_otomatik_dagitim_mantigi_fifo_calısir(): void
    {
        config([
            'muhasebe.otomasyon.finans_otomatik_dagitim' => false,
            'muhasebe.otomasyon.avans_otomatik_mahsup' => false,
        ]);
        $firma = $this->firmaOlustur('FK12');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, CariTuru::Musteri);
        $kasa = $this->kasaOlustur($firma);
        $f1 = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::GidenFatura);
        $f2 = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::GidenFatura);
        $f3 = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::GidenFatura);
        $f1->update(['acik_tutar' => '40']);
        $f2->update(['acik_tutar' => '30']);
        $f3->update(['acik_tutar' => '60']);

        $finans = app(FinansHareketServisi::class)
            ->tahsilatKasadanKaydet($firma->id, $cari->id, $kasa->id, '100', 'TRY', now(), 'oner')['finans'];

        $oneriler = app(FaturaFinansKapamaServisi::class)->onerilenDagitimOlustur($finans, 'fifo');
        $this->assertCount(3, $oneriler);
        $this->assertSame($f1->id, $oneriler[0]['fatura_id']);
        $this->assertSame('40.00', number_format((float) $oneriler[0]['tutar'], 2, '.', ''));
        $this->assertSame('30.00', number_format((float) $oneriler[1]['tutar'], 2, '.', ''));
        $this->assertSame('30.00', number_format((float) $oneriler[2]['tutar'], 2, '.', ''));
    }

    public function test_cari_avans_toplami_dogru_hesaplanir(): void
    {
        $firma = $this->firmaOlustur('FK13');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, CariTuru::Musteri);
        $kasa = $this->kasaOlustur($firma);
        $fatura = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::GidenFatura);
        app(FinansHareketServisi::class)->tahsilatKasadanKaydet($firma->id, $cari->id, $kasa->id, '130', 'TRY', now(), 'fazla', 'fatura', $fatura->id);

        $ozet = app(FaturaFinansKapamaServisi::class)->cariKullanilabilirAvansOzeti($firma->id, $cari->id, 'TRY');
        $this->assertSame('10.00', number_format((float) $ozet['toplam_avans'], 2, '.', ''));
        $this->assertSame(1, $ozet['satir_sayisi']);
    }

    public function test_reconciliation_komutu_tutarsizligi_bulur_ve_dry_run_guvenli_calısır(): void
    {
        $firma = $this->firmaOlustur('FK14');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, CariTuru::Musteri);
        $kasa = $this->kasaOlustur($firma);
        $fatura = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::GidenFatura);
        app(FinansHareketServisi::class)->tahsilatKasadanKaydet($firma->id, $cari->id, $kasa->id, '20', 'TRY', now(), 'a', 'fatura', $fatura->id);
        $fatura->update(['odendi_tutari' => '999']);

        $this->artisan('fatura:kapama-dogrula --dry-run --fatura_id='.$fatura->id)
            ->expectsOutputToContain('Hatalı: 1')
            ->assertExitCode(1);
        $this->assertSame('999.00', number_format((float) $fatura->fresh()->odendi_tutari, 2, '.', ''));
    }

    public function test_onay_sonrasi_avans_otomatik_mahsup(): void
    {
        $firma = $this->firmaOlustur('FK16');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, CariTuru::Musteri);
        $kasa = $this->kasaOlustur($firma);
        $f1 = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::GidenFatura);
        app(FinansHareketServisi::class)->tahsilatKasadanKaydet($firma->id, $cari->id, $kasa->id, '130', 'TRY', now(), 'fazla', 'fatura', $f1->id);

        $f2 = Fatura::query()->create([
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
            'tur' => FaturaTuru::GidenFatura->value,
            'durum' => FaturaDurumu::Taslak->value,
            'tarih' => now(),
            'ara_toplam' => '100',
            'kdv_toplam' => '20',
            'genel_toplam' => '120',
            'genel_indirim_tutari' => '0',
            'toplam_indirim' => '0',
            'odenecek_tutar' => '120',
            'odendi_tutari' => '0',
            'acik_tutar' => '120',
            'odeme_durumu' => 'odenmedi',
            'para_birimi' => 'TRY',
            'doviz_kuru' => '1',
        ]);
        FaturaKalemi::query()->create([
            'firma_id' => $firma->id,
            'fatura_id' => $f2->id,
            'satir_no' => 1,
            'kalem_tipi' => 'hizmet_kalemi',
            'hizmet_mi' => true,
            'miktar' => '1',
            'birim_fiyat' => '100',
            'kdv_orani' => '20',
            'net_tutar' => '100',
            'kdv_tutari' => '20',
            'toplam' => '120',
            'satir_toplami' => '100',
            'satir_genel_toplam' => '120',
            'para_birimi' => 'TRY',
        ]);

        app(FaturaIslemServisi::class)->faturayiOnayla($f2->fresh());

        $f2 = $f2->fresh();
        $this->assertSame('10.00', number_format((float) $f2->odendi_tutari, 2, '.', ''));
        $this->assertSame('110.00', number_format((float) $f2->acik_tutar, 2, '.', ''));
        $this->assertSame('kismi_odendi', $f2->odeme_durumu);
    }

    public function test_avans_otomasyon_kapali_iken_onay_mahsup_etmez(): void
    {
        config(['muhasebe.otomasyon.avans_otomatik_mahsup' => false]);
        $firma = $this->firmaOlustur('FK16B');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, CariTuru::Musteri);
        $kasa = $this->kasaOlustur($firma);
        $f1 = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::GidenFatura);
        app(FinansHareketServisi::class)->tahsilatKasadanKaydet($firma->id, $cari->id, $kasa->id, '130', 'TRY', now(), 'fazla', 'fatura', $f1->id);

        $f2 = Fatura::query()->create([
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
            'tur' => FaturaTuru::GidenFatura->value,
            'durum' => FaturaDurumu::Taslak->value,
            'tarih' => now(),
            'ara_toplam' => '100',
            'kdv_toplam' => '20',
            'genel_toplam' => '120',
            'genel_indirim_tutari' => '0',
            'toplam_indirim' => '0',
            'odenecek_tutar' => '120',
            'odendi_tutari' => '0',
            'acik_tutar' => '120',
            'odeme_durumu' => 'odenmedi',
            'para_birimi' => 'TRY',
            'doviz_kuru' => '1',
        ]);
        FaturaKalemi::query()->create([
            'firma_id' => $firma->id,
            'fatura_id' => $f2->id,
            'satir_no' => 1,
            'kalem_tipi' => 'hizmet_kalemi',
            'hizmet_mi' => true,
            'miktar' => '1',
            'birim_fiyat' => '100',
            'kdv_orani' => '20',
            'net_tutar' => '100',
            'kdv_tutari' => '20',
            'toplam' => '120',
            'satir_toplami' => '100',
            'satir_genel_toplam' => '120',
            'para_birimi' => 'TRY',
        ]);

        app(FaturaIslemServisi::class)->faturayiOnayla($f2->fresh());

        $f2 = $f2->fresh();
        $this->assertSame('0.00', number_format((float) $f2->odendi_tutari, 2, '.', ''));
        $this->assertSame('120.00', number_format((float) $f2->acik_tutar, 2, '.', ''));
        $this->assertSame('odenmedi', $f2->odeme_durumu);
    }

    public function test_tahsilat_referanssiz_otomatik_coklu_fatura_kapar(): void
    {
        $firma = $this->firmaOlustur('FK17');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, CariTuru::Musteri);
        $kasa = $this->kasaOlustur($firma);
        $f1 = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::GidenFatura);
        $f2 = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::GidenFatura);
        $f3 = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::GidenFatura);
        $f1->update(['odenecek_tutar' => '400', 'acik_tutar' => '400', 'genel_toplam' => '400']);
        $f2->update(['odenecek_tutar' => '300', 'acik_tutar' => '300', 'genel_toplam' => '300']);
        $f3->update(['odenecek_tutar' => '300', 'acik_tutar' => '300', 'genel_toplam' => '300']);

        $finans = app(FinansHareketServisi::class)
            ->tahsilatKasadanKaydet($firma->id, $cari->id, $kasa->id, '1000', 'TRY', now(), 'oto')['finans'];

        $this->assertSame('1000.00', number_format((float) $finans->fresh()->kullanilan_tutar, 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $finans->fresh()->avans_tutar, 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $f1->fresh()->acik_tutar, 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $f2->fresh()->acik_tutar, 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $f3->fresh()->acik_tutar, 2, '.', ''));
    }

    public function test_finans_terslenince_coklu_kapama_faturalari_yenilenir(): void
    {
        $firma = $this->firmaOlustur('FK18');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, CariTuru::Musteri);
        $kasa = $this->kasaOlustur($firma);
        $f1 = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::GidenFatura);
        $f2 = $this->onayliFaturaOlustur($firma, $cari, FaturaTuru::GidenFatura);

        $finans = app(FinansHareketServisi::class)
            ->tahsilatKasadanKaydet($firma->id, $cari->id, $kasa->id, '240', 'TRY', now(), 'coklu')['finans'];

        $this->assertSame('0.00', number_format((float) $f1->fresh()->acik_tutar, 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $f2->fresh()->acik_tutar, 2, '.', ''));

        app(FinansHareketServisi::class)->tersKayitOlustur($finans->fresh());

        $this->assertSame('120.00', number_format((float) $f1->fresh()->acik_tutar, 2, '.', ''));
        $this->assertSame('120.00', number_format((float) $f2->fresh()->acik_tutar, 2, '.', ''));
        $this->assertSame('odenmedi', $f1->fresh()->odeme_durumu);
        $this->assertSame('odenmedi', $f2->fresh()->odeme_durumu);
    }

    public function test_finans_ile_fatura_para_birimi_farkli_dagitim_engellenir(): void
    {
        $firma = $this->firmaOlustur('FK19');
        $this->superAdminVeSession($firma);
        $cariTry = $this->cariOlustur($firma, CariTuru::Musteri);
        $kasa = $this->kasaOlustur($firma);
        $cariEur = Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'C-EUR-'.uniqid(),
            'ad' => 'Cari EUR',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'EUR',
        ]);
        $finans = app(FinansHareketServisi::class)
            ->tahsilatKasadanKaydet($firma->id, $cariTry->id, $kasa->id, '100', 'TRY', now(), 'try')['finans'];

        $fEur = Fatura::query()->create([
            'firma_id' => $firma->id,
            'cari_id' => $cariEur->id,
            'tur' => FaturaTuru::GidenFatura->value,
            'durum' => FaturaDurumu::Onayli->value,
            'tarih' => now(),
            'ara_toplam' => '100',
            'kdv_toplam' => '0',
            'genel_toplam' => '100',
            'genel_indirim_tutari' => '0',
            'toplam_indirim' => '0',
            'odenecek_tutar' => '100',
            'odendi_tutari' => '0',
            'acik_tutar' => '100',
            'odeme_durumu' => 'odenmedi',
            'para_birimi' => 'EUR',
            'doviz_kuru' => '1',
        ]);

        $this->expectException(IsKuraliIstisnasi::class);
        app(FaturaFinansKapamaServisi::class)->finansiFaturalaraDagit($finans, [
            ['fatura_id' => $fEur->id, 'tutar' => '50'],
        ]);
    }
}
