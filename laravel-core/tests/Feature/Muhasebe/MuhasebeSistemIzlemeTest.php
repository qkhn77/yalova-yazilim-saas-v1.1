<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Fatura;
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
use App\Muhasebe\Servisler\MuhasebeSistemDogrulamaServisi;
use App\Muhasebe\Servisler\StokHareketServisi;
use App\Muhasebe\Servisler\StokIzlemeServisi;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class MuhasebeSistemIzlemeTest extends TestCase
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

    private function cari(Firma $firma): Cari
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

    /**
     * Negatif stok izinli iken kritik izleme yolu (negative_flag + üretim logları stok.negatif_kritik / stok.negatif_olustu).
     * Laravel 11 Log facade fake yok; davranış bayrak ve miktar ile doğrulanır.
     */
    public function test_negatif_stok_izinli_kritik_yolu_ve_bayrak(): void
    {
        config(['muhasebe.stok.negatif_stok_izinli' => true]);
        config(['muhasebe.stok.negatif_stok_kritik_esik' => null]);

        $firma = $this->firmaOlustur('MS1');
        $this->superAdminVeSession($firma);
        $stok = StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'S-'.uniqid(),
            'ad' => 'Stok',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'stok_takip' => true,
            'stok_miktari' => '1',
            'para_birimi' => 'TRY',
            'guncel_birim_maliyet' => '0',
            'stok_degeri' => '0',
        ]);

        app(StokHareketServisi::class)->kayitOlustur($firma->id, [
            'stok_id' => $stok->id,
            'islem_turu' => StokHareketIslemTuru::Satis,
            'miktar' => '2',
            'birim_fiyat' => '10',
            'belge_turu' => StokBelgeTuru::Fatura,
            'belge_id' => 9001,
            'tarih' => now(),
        ]);

        $g = $stok->fresh();
        $this->assertTrue((bool) $g->negative_flag);
        $this->assertSame('-1.0000', number_format((float) $g->stok_miktari, 4, '.', ''));
    }

    public function test_negatif_stok_esik_asiminda_error_path_tetiklenir(): void
    {
        config(['muhasebe.stok.negatif_stok_izinli' => true]);
        config(['muhasebe.stok.negatif_stok_kritik_esik' => '0.5']);

        $firma = $this->firmaOlustur('MS2');
        $this->superAdminVeSession($firma);
        $stok = StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'S-'.uniqid(),
            'ad' => 'Stok',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'stok_takip' => true,
            'stok_miktari' => '1',
            'para_birimi' => 'TRY',
            'guncel_birim_maliyet' => '0',
            'stok_degeri' => '0',
        ]);

        app(StokHareketServisi::class)->kayitOlustur($firma->id, [
            'stok_id' => $stok->id,
            'islem_turu' => StokHareketIslemTuru::Satis,
            'miktar' => '2',
            'birim_fiyat' => '10',
            'belge_turu' => StokBelgeTuru::Fatura,
            'belge_id' => 9002,
            'tarih' => now(),
        ]);

        $this->assertTrue((bool) $stok->fresh()->negative_flag);
    }

    public function test_stok_negatif_durumlari_getir(): void
    {
        $firma = $this->firmaOlustur('MS3');
        StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'S-'.uniqid(),
            'ad' => 'Stok',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'stok_takip' => true,
            'stok_miktari' => '-1',
            'negative_flag' => true,
            'para_birimi' => 'TRY',
        ]);

        $liste = app(StokIzlemeServisi::class)->stokNegatifDurumlariGetir($firma->id);
        $this->assertCount(1, $liste);
    }

    public function test_zincir_bozuk_hard_fail_komut_basarisiz(): void
    {
        config(['muhasebe.stok.zincir_hata_hard_fail' => true]);
        config(['muhasebe.stok.rebuild_canli_izinli' => true]);

        $firma = $this->firmaOlustur('MS4');
        $this->superAdminVeSession($firma);
        $stok = StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'S-'.uniqid(),
            'ad' => 'Stok',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'stok_takip' => true,
            'stok_miktari' => '0',
            'para_birimi' => 'TRY',
            'guncel_birim_maliyet' => '0',
            'stok_degeri' => '0',
        ]);

        StokHareketi::query()->create([
            'firma_id' => $firma->id,
            'stok_id' => $stok->id,
            'islem_turu' => StokHareketIslemTuru::Alis,
            'miktar' => '1',
            'onceki_miktar' => '0',
            'sonraki_miktar' => '1',
            'birim_fiyat' => '10',
            'birim_maliyet' => '10',
            'toplam' => '10',
            'toplam_maliyet' => '10',
            'belge_turu' => StokBelgeTuru::Fatura,
            'referans_tipi' => StokBelgeTuru::Fatura->value,
            'belge_id' => 9101,
            'referans_id' => 9101,
            'tarih' => now(),
            'islem_tarihi' => now(),
            'durum' => StokHareketDurumu::Aktif,
        ]);
        StokHareketi::query()->create([
            'firma_id' => $firma->id,
            'stok_id' => $stok->id,
            'islem_turu' => StokHareketIslemTuru::Satis,
            'miktar' => '1',
            'onceki_miktar' => '5',
            'sonraki_miktar' => '4',
            'birim_fiyat' => '10',
            'birim_maliyet' => '10',
            'toplam' => '10',
            'toplam_maliyet' => '10',
            'belge_turu' => StokBelgeTuru::Fatura,
            'referans_tipi' => StokBelgeTuru::Fatura->value,
            'belge_id' => 9102,
            'referans_id' => 9102,
            'tarih' => now(),
            'islem_tarihi' => now(),
            'durum' => StokHareketDurumu::Aktif,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stok zinciri bozuk');
        $this->artisan('stok:maliyet-yeniden-hesapla', ['--stok_id' => $stok->id]);
    }

    public function test_sistem_dogrulama_fatura_cari_eksik_yakalar(): void
    {
        $firma = $this->firmaOlustur('MS5');
        $cari = $this->cari($firma);
        Fatura::query()->create([
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
            'tur' => FaturaTuru::Giden->value,
            'durum' => FaturaDurumu::Onayli->value,
            'tarih' => now(),
            'ara_toplam' => '100',
            'kdv_toplam' => '20',
            'genel_toplam' => '120',
            'genel_indirim_tutari' => '0',
            'toplam_indirim' => '0',
            'odenecek_tutar' => '120',
            'odendi_tutari' => '0',
            'acik_tutar' => '120',
            'para_birimi' => 'TRY',
            'doviz_kuru' => '1',
        ]);

        $hatalar = app(MuhasebeSistemDogrulamaServisi::class)->sistemTutarlilikKontrolu($firma->id, false);
        $this->assertNotEmpty($hatalar);
        $this->assertSame('fatura_cari_eksik', $hatalar[0]['kod']);
    }

    public function test_muhasebe_sistem_dogrula_komutu_cikti_verir(): void
    {
        $firma = $this->firmaOlustur('MS6');
        $cari = $this->cari($firma);
        Fatura::query()->create([
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
            'tur' => FaturaTuru::Giden->value,
            'durum' => FaturaDurumu::Onayli->value,
            'tarih' => now(),
            'ara_toplam' => '100',
            'kdv_toplam' => '20',
            'genel_toplam' => '120',
            'genel_indirim_tutari' => '0',
            'toplam_indirim' => '0',
            'odenecek_tutar' => '120',
            'odendi_tutari' => '0',
            'acik_tutar' => '120',
            'para_birimi' => 'TRY',
            'doviz_kuru' => '1',
        ]);

        $this->artisan('muhasebe:sistem-dogrula', [
            '--firma_id' => $firma->id,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('fatura_cari_eksik')
            ->assertExitCode(1);
    }
}
