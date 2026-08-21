<?php

namespace Tests\Feature\Maintenance;

use App\Models\Ecommerce\Siparis;
use App\Models\Ecommerce\SiparisKalemi;
use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\StokKarti;
use App\Models\User;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Servisler\FaturaIslemServisi;
use App\Muhasebe\Servisler\FinansHareketServisi;
use App\Services\FirmaAyarDeposu;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationToolsTest extends TestCase
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

    private function cariOlustur(Firma $firma): Cari
    {
        return Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'C-'.uniqid(),
            'ad' => 'Cari',
            'tur' => CariTuru::Musteri->value,
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

    private function onayliFatura(Firma $firma, Cari $cari): Fatura
    {
        $f = Fatura::query()->create([
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
            'fatura_id' => $f->id,
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
        app(FaturaIslemServisi::class)->faturayiOnayla($f);

        return $f->fresh();
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

    public function test_tutarsizlik_tespit_edilir_ve_dry_run_veri_degistirmez(): void
    {
        $firma = $this->firmaOlustur('R1');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma);
        $kasa = $this->kasaOlustur($firma);
        $fatura = $this->onayliFatura($firma, $cari);

        app(FinansHareketServisi::class)->tahsilatKasadanKaydet($firma->id, $cari->id, $kasa->id, '20', 'TRY', now(), 'test', 'fatura', $fatura->id);
        $fatura->update(['odendi_tutari' => '999.00']);

        $this->artisan('muhasebe:reconcile --firma_id='.$firma->id)->assertExitCode(1);
        $this->assertSame('999.00', number_format((float) $fatura->fresh()->odendi_tutari, 2, '.', ''));
    }

    public function test_fix_dogru_duzeltir_ve_duplicate_fix_olusturmaz(): void
    {
        $firma = $this->firmaOlustur('R2');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma);
        $kasa = $this->kasaOlustur($firma);
        $fatura = $this->onayliFatura($firma, $cari);

        $finans = app(FinansHareketServisi::class)->tahsilatKasadanKaydet($firma->id, $cari->id, $kasa->id, '130', 'TRY', now(), 'fazla', 'fatura', $fatura->id)['finans'];
        $fatura->update(['odendi_tutari' => '0.00', 'acik_tutar' => '120.00']);
        $finans->update(['kullanilan_tutar' => '0.00', 'avans_tutar' => '0.00']);

        $this->artisan('muhasebe:reconcile --firma_id='.$firma->id.' --fix')->assertExitCode(1);
        $this->assertSame('120.00', number_format((float) $fatura->fresh()->odendi_tutari, 2, '.', ''));

        $onceki = $finans->fresh();
        $this->artisan('muhasebe:reconcile --firma_id='.$firma->id.' --fix')->assertExitCode(0);
        $sonraki = $finans->fresh();
        $this->assertSame((string) $onceki->kullanilan_tutar, (string) $sonraki->kullanilan_tutar);
        $this->assertSame((string) $onceki->avans_tutar, (string) $sonraki->avans_tutar);
    }

    public function test_siparis_odeme_finans_uyumsuzlugu_yakalanir_ve_fixlenir(): void
    {
        $firma = $this->firmaOlustur('R3');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma);
        $kasa = $this->kasaOlustur($firma);
        app(FirmaAyarDeposu::class)->yaz($firma->id, 'ecommerce_tahsilat_cari_id', $cari->id);
        app(FirmaAyarDeposu::class)->yaz($firma->id, 'ecommerce_tahsilat_kasa_id', $kasa->id);

        $siparis = Siparis::query()->create([
            'siparis_no' => 'SIP-R3',
            'firma_id' => $firma->id,
            'musteri_ad_soyad' => 'A',
            'musteri_email' => 'a@test.local',
            'musteri_telefon' => '555',
            'teslimat_adresi' => 'Adres',
            'para_birimi' => 'TRY',
            'ara_toplam' => '100',
            'kdv_toplam' => '0',
            'genel_toplam' => '100',
            'durum' => Siparis::DURUM_ONAYLANDI_YENI,
            'stok_dusuldu_mi' => true,
            'odeme_deneme_sayisi' => 0,
        ]);

        $this->artisan('ecommerce:reconcile --firma_id='.$firma->id)->assertExitCode(1);
    }

    public function test_stok_rezerv_sapmasi_duzeltilebilir(): void
    {
        $firma = $this->firmaOlustur('R4');
        $stok = StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'STK-R4',
            'ad' => 'Stok',
            'tur' => 'diger',
            'durum' => 'aktif',
            'stok_takip' => true,
            'stok_miktari' => '10',
            'rezerve_miktar' => '7.0000',
        ]);
        $siparis = Siparis::query()->create([
            'siparis_no' => 'SIP-R4',
            'firma_id' => $firma->id,
            'musteri_ad_soyad' => 'A',
            'musteri_email' => 'a@test.local',
            'musteri_telefon' => '555',
            'teslimat_adresi' => 'Adres',
            'para_birimi' => 'TRY',
            'ara_toplam' => '100',
            'kdv_toplam' => '0',
            'genel_toplam' => '100',
            'durum' => Siparis::DURUM_ONAY_BEKLIYOR,
            'stok_dusuldu_mi' => false,
            'odeme_deneme_sayisi' => 0,
        ]);
        SiparisKalemi::query()->create([
            'siparis_id' => $siparis->id,
            'stok_karti_id' => $stok->id,
            'urun_adi_snapshot' => 'X',
            'urun_kodu_snapshot' => 'X',
            'miktar' => '1',
            'stok_rezerv_miktari' => '2.0000',
            'birim_fiyat' => '100',
            'kdv_orani' => '0',
            'satir_toplami' => '100',
        ]);

        $this->artisan('stok:rezerv-dogrula --firma_id='.$firma->id.' --fix')->assertExitCode(1);
        $this->assertSame('2.0000', number_format((float) $stok->fresh()->rezerve_miktar, 4, '.', ''));
    }

    public function test_avans_kapama_tutarsizligi_duzgun_ele_alinir(): void
    {
        $firma = $this->firmaOlustur('R5');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma);
        $kasa = $this->kasaOlustur($firma);
        $fatura = $this->onayliFatura($firma, $cari);
        $finans = app(FinansHareketServisi::class)
            ->tahsilatKasadanKaydet($firma->id, $cari->id, $kasa->id, '130', 'TRY', now(), 'fazla', 'fatura', $fatura->id)['finans'];

        $finans->update(['kullanilan_tutar' => '999.00', 'avans_tutar' => '999.00']);
        $this->artisan('muhasebe:reconcile --firma_id='.$firma->id.' --fix')->assertExitCode(1);

        $this->assertSame('120.00', number_format((float) $finans->fresh()->kullanilan_tutar, 2, '.', ''));
        $this->assertSame('10.00', number_format((float) $finans->fresh()->avans_tutar, 2, '.', ''));
    }
}
