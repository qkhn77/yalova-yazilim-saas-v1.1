<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\StokHareketi;
use App\Models\Muhasebe\StokKarti;
use App\Models\User;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokBelgeTuru;
use App\Muhasebe\Enumlar\StokHareketDurumu;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Servisler\FaturaIslemServisi;
use App\Muhasebe\Servisler\StokDegerlemeServisi;
use App\Muhasebe\Servisler\StokHareketServisi;
use App\Muhasebe\Servisler\StokMaliyetHesaplamaServisi;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StokMaliyetDegerlemeTest extends TestCase
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

    private function stok(Firma $firma, string $miktar = '0'): StokKarti
    {
        return StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'S-'.uniqid(),
            'ad' => 'Stok',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'stok_takip' => true,
            'stok_miktari' => $miktar,
            'para_birimi' => 'TRY',
            'guncel_birim_maliyet' => '0',
            'stok_degeri' => '0',
        ]);
    }

    private function cari(Firma $firma): Cari
    {
        return Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'C-'.uniqid(),
            'ad' => 'Cari',
            'tur' => CariTuru::Tedarikci->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);
    }

    public function test_alis_hareketi_maliyeti_dogru_artirir_ve_satis_cikista_ortalama_kullanir(): void
    {
        $firma = $this->firmaOlustur('SM1');
        $this->superAdminVeSession($firma);
        $stok = $this->stok($firma, '0');
        $servis = app(StokHareketServisi::class);

        $servis->kayitOlustur($firma->id, [
            'stok_id' => $stok->id,
            'islem_turu' => StokHareketIslemTuru::Alis,
            'miktar' => '10',
            'birim_fiyat' => '5',
            'belge_turu' => StokBelgeTuru::Fatura,
            'belge_id' => 1,
            'tarih' => now(),
        ]);
        $this->assertSame('5.00', number_format((float) $stok->fresh()->guncel_birim_maliyet, 2, '.', ''));
        $this->assertSame('50.00', number_format((float) $stok->fresh()->stok_degeri, 2, '.', ''));

        $servis->kayitOlustur($firma->id, [
            'stok_id' => $stok->id,
            'islem_turu' => StokHareketIslemTuru::Satis,
            'miktar' => '4',
            'birim_fiyat' => '12',
            'belge_turu' => StokBelgeTuru::Fatura,
            'belge_id' => 2,
            'tarih' => now(),
        ]);
        $this->assertSame('5.00', number_format((float) $stok->fresh()->guncel_birim_maliyet, 2, '.', ''));
        $this->assertSame('30.00', number_format((float) $stok->fresh()->stok_degeri, 2, '.', ''));
        $this->assertSame('6.0000', number_format((float) $stok->fresh()->stok_miktari, 4, '.', ''));
    }

    public function test_satis_iadesi_ve_alis_iadesi_maliyet_yonu_dogru(): void
    {
        $firma = $this->firmaOlustur('SM2');
        $this->superAdminVeSession($firma);
        $stok = $this->stok($firma, '0');
        $servis = app(StokHareketServisi::class);
        $servis->kayitOlustur($firma->id, ['stok_id' => $stok->id, 'islem_turu' => StokHareketIslemTuru::Alis, 'miktar' => '10', 'birim_fiyat' => '5', 'belge_turu' => StokBelgeTuru::Fatura, 'belge_id' => 1, 'tarih' => now()]);

        $servis->kayitOlustur($firma->id, ['stok_id' => $stok->id, 'islem_turu' => StokHareketIslemTuru::SatisIadesi, 'miktar' => '2', 'birim_fiyat' => '5', 'belge_turu' => StokBelgeTuru::Fatura, 'belge_id' => 2, 'tarih' => now()]);
        $this->assertSame('12.0000', number_format((float) $stok->fresh()->stok_miktari, 4, '.', ''));

        $servis->kayitOlustur($firma->id, ['stok_id' => $stok->id, 'islem_turu' => StokHareketIslemTuru::AlisIadesi, 'miktar' => '2', 'birim_fiyat' => '5', 'belge_turu' => StokBelgeTuru::Fatura, 'belge_id' => 3, 'tarih' => now()]);
        $this->assertSame('10.0000', number_format((float) $stok->fresh()->stok_miktari, 4, '.', ''));
    }

    public function test_proforma_ve_bekleyen_maliyet_uretmez_ve_gider_faturasi_stok_hareketi_uretmez(): void
    {
        $firma = $this->firmaOlustur('SM3');
        $this->superAdminVeSession($firma);
        $stok = $this->stok($firma, '5');
        $f = Fatura::query()->create([
            'firma_id' => $firma->id, 'tur' => FaturaTuru::ProformaFatura->value, 'durum' => FaturaDurumu::Taslak->value,
            'tarih' => now(), 'ara_toplam' => '100', 'kdv_toplam' => '20', 'genel_toplam' => '120', 'odenecek_tutar' => '120', 'acik_tutar' => '120', 'para_birimi' => 'TRY',
        ]);
        FaturaKalemi::query()->create([
            'firma_id' => $firma->id, 'fatura_id' => $f->id, 'satir_no' => 1, 'kalem_tipi' => 'stok_kalemi', 'stok_id' => $stok->id,
            'miktar' => '1', 'birim_fiyat' => '100', 'kdv_orani' => '20', 'net_tutar' => '100', 'kdv_tutari' => '20', 'toplam' => '120', 'satir_toplami' => '100', 'satir_genel_toplam' => '120', 'para_birimi' => 'TRY',
        ]);
        app(FaturaIslemServisi::class)->faturayiOnayla($f->fresh());
        $this->assertSame('5.0000', number_format((float) $stok->fresh()->stok_miktari, 4, '.', ''));

        $gider = Fatura::query()->create([
            'firma_id' => $firma->id, 'tur' => FaturaTuru::GiderFaturasi->value, 'durum' => FaturaDurumu::Taslak->value,
            'tarih' => now(), 'ara_toplam' => '100', 'kdv_toplam' => '20', 'genel_toplam' => '120', 'odenecek_tutar' => '120', 'acik_tutar' => '120', 'para_birimi' => 'TRY', 'cari_id' => $this->cari($firma)->id,
        ]);
        FaturaKalemi::query()->create([
            'firma_id' => $firma->id, 'fatura_id' => $gider->id, 'satir_no' => 1, 'kalem_tipi' => 'hizmet_kalemi',
            'hizmet_mi' => true, 'miktar' => '1', 'birim_fiyat' => '100', 'kdv_orani' => '20', 'net_tutar' => '100', 'kdv_tutari' => '20', 'toplam' => '120',
            'satir_toplami' => '100', 'satir_genel_toplam' => '120', 'para_birimi' => 'TRY',
        ]);
        app(FaturaIslemServisi::class)->faturayiOnayla($gider->fresh());
        $this->assertSame('5.0000', number_format((float) $stok->fresh()->stok_miktari, 4, '.', ''));
    }

    public function test_stok_degerleme_servisi_dogru_hesaplar_ve_kategori_ozeti_uretir(): void
    {
        $firma = $this->firmaOlustur('SM4');
        $this->superAdminVeSession($firma);
        $stok = $this->stok($firma, '0');
        app(StokHareketServisi::class)->kayitOlustur($firma->id, [
            'stok_id' => $stok->id, 'islem_turu' => StokHareketIslemTuru::Alis, 'miktar' => '3', 'birim_fiyat' => '10',
            'belge_turu' => StokBelgeTuru::Fatura, 'belge_id' => 10, 'tarih' => now(),
        ]);
        $degerleme = app(StokDegerlemeServisi::class);
        $this->assertSame('30.00', number_format((float) $degerleme->firmaToplamDeger($firma->id), 2, '.', ''));
        $this->assertCount(1, $degerleme->kategoriBazliDeger($firma->id));
    }

    public function test_stok_maliyet_yeniden_hesapla_komutu_tutarsizlik_bulur_ve_dry_run_guvenli(): void
    {
        $firma = $this->firmaOlustur('SM5');
        $this->superAdminVeSession($firma);
        $stok = $this->stok($firma, '0');
        app(StokHareketServisi::class)->kayitOlustur($firma->id, [
            'stok_id' => $stok->id, 'islem_turu' => StokHareketIslemTuru::Alis, 'miktar' => '2', 'birim_fiyat' => '10',
            'belge_turu' => StokBelgeTuru::Fatura, 'belge_id' => 11, 'tarih' => now(),
        ]);
        $stok->update(['guncel_birim_maliyet' => '999']);

        $this->artisan('stok:maliyet-yeniden-hesapla --dry-run --stok_id='.$stok->id)
            ->expectsOutputToContain('Tutarsız')
            ->assertExitCode(1);
        $this->assertSame('999.00', number_format((float) $stok->fresh()->guncel_birim_maliyet, 2, '.', ''));
    }

    public function test_stok_maliyet_yeniden_hesapla_yazar_modda_duzeltir(): void
    {
        $firma = $this->firmaOlustur('SM6');
        $this->superAdminVeSession($firma);
        $stok = $this->stok($firma, '0');
        app(StokHareketServisi::class)->kayitOlustur($firma->id, [
            'stok_id' => $stok->id, 'islem_turu' => StokHareketIslemTuru::Alis, 'miktar' => '2', 'birim_fiyat' => '10',
            'belge_turu' => StokBelgeTuru::Fatura, 'belge_id' => 12, 'tarih' => now(),
        ]);
        $stok->update(['guncel_birim_maliyet' => '999']);

        $this->artisan('stok:maliyet-yeniden-hesapla --stok_id='.$stok->id)->assertExitCode(1);
        $this->assertSame('10.00', number_format((float) $stok->fresh()->guncel_birim_maliyet, 2, '.', ''));
    }

    public function test_tenant_diger_firmanin_stok_maliyetini_etkileyemez(): void
    {
        $f1 = $this->firmaOlustur('SM7A');
        $f2 = $this->firmaOlustur('SM7B');
        $this->superAdminVeSession($f1);
        $s1 = $this->stok($f1, '0');
        $s2 = $this->stok($f2, '0');
        app(StokHareketServisi::class)->kayitOlustur($f1->id, [
            'stok_id' => $s1->id, 'islem_turu' => StokHareketIslemTuru::Alis, 'miktar' => '1', 'birim_fiyat' => '10',
            'belge_turu' => StokBelgeTuru::Fatura, 'belge_id' => 13, 'tarih' => now(),
        ]);
        $this->assertSame('0.00', number_format((float) $s2->fresh()->stok_degeri, 2, '.', ''));
    }

    public function test_negatif_stok_engellenir_izinli_degilse(): void
    {
        config()->set('muhasebe.stok.negatif_stok_izinli', false);
        $firma = $this->firmaOlustur('SM8');
        $this->superAdminVeSession($firma);
        $stok = $this->stok($firma, '1');

        $this->expectException(IsKuraliIstisnasi::class);
        app(StokHareketServisi::class)->kayitOlustur($firma->id, [
            'stok_id' => $stok->id,
            'islem_turu' => StokHareketIslemTuru::Satis,
            'miktar' => '2',
            'birim_fiyat' => '10',
            'belge_turu' => StokBelgeTuru::Fatura,
            'belge_id' => 14,
            'tarih' => now(),
        ]);
    }

    public function test_negatif_stok_olusur_ve_flag_set_edilir_izinliyse(): void
    {
        config()->set('muhasebe.stok.negatif_stok_izinli', true);
        $firma = $this->firmaOlustur('SM9');
        $this->superAdminVeSession($firma);
        $stok = $this->stok($firma, '1');

        app(StokHareketServisi::class)->kayitOlustur($firma->id, [
            'stok_id' => $stok->id,
            'islem_turu' => StokHareketIslemTuru::Satis,
            'miktar' => '2',
            'birim_fiyat' => '10',
            'belge_turu' => StokBelgeTuru::Fatura,
            'belge_id' => 15,
            'tarih' => now(),
        ]);

        $this->assertSame('-1.0000', number_format((float) $stok->fresh()->stok_miktari, 4, '.', ''));
        $this->assertTrue((bool) $stok->fresh()->negative_flag);
    }

    public function test_rebuild_komutu_canlida_bloklanir_config_false(): void
    {
        $this->app['env'] = 'production';
        config()->set('muhasebe.stok.rebuild_canli_izinli', false);
        $this->artisan('stok:maliyet-yeniden-hesapla --dry-run')
            ->expectsOutputToContain('Canlı ortamda stok maliyet rebuild kapalı')
            ->assertExitCode(1);
    }

    public function test_zincir_bozulmasi_tespit_edilir(): void
    {
        $firma = $this->firmaOlustur('SM10');
        $this->superAdminVeSession($firma);
        $stok = $this->stok($firma, '0');
        StokHareketi::query()->create([
            'firma_id' => $firma->id,
            'stok_id' => $stok->id,
            'islem_turu' => StokHareketIslemTuru::Alis,
            'miktar' => '1',
            'onceki_miktar' => '5', // zincir bozuk
            'sonraki_miktar' => '6',
            'birim_fiyat' => '10',
            'birim_maliyet' => '10',
            'toplam' => '10',
            'toplam_maliyet' => '5', // maliyet de bozuk
            'belge_turu' => StokBelgeTuru::Fatura,
            'referans_tipi' => StokBelgeTuru::Fatura->value,
            'belge_id' => 16,
            'referans_id' => 16,
            'tarih' => now(),
            'islem_tarihi' => now(),
            'durum' => StokHareketDurumu::Aktif,
        ]);

        $rapor = app(StokMaliyetHesaplamaServisi::class)->stokZincirSaglikKontrolu($stok->id);
        $this->assertFalse($rapor['saglikli']);
        $this->assertNotEmpty($rapor['sorunlar']);

        $this->artisan('stok:maliyet-yeniden-hesapla --dry-run --stok_id='.$stok->id)
            ->expectsOutputToContain('BOZUK')
            ->assertExitCode(1);
    }
}
