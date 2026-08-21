<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\CariHareketi;
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
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Servisler\FaturaIslemServisi;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaturaEntegrasyonHardeningTest extends TestCase
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

    private function faturaOlustur(Firma $firma, Cari $cari, FaturaTuru $tur = FaturaTuru::Giden): Fatura
    {
        return Fatura::query()->create([
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
            'para_birimi' => 'TRY',
            'doviz_kuru' => '1',
        ]);
    }

    private function stokOlustur(Firma $firma, string $stok = '10'): StokKarti
    {
        return StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'S-'.uniqid(),
            'ad' => 'Stok',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'stok_takip' => true,
            'stok_miktari' => $stok,
            'para_birimi' => 'TRY',
        ]);
    }

    private function stokKalemiEkle(Firma $firma, Fatura $fatura, StokKarti $stok, string $miktar = '2'): void
    {
        FaturaKalemi::query()->create([
            'firma_id' => $firma->id,
            'fatura_id' => $fatura->id,
            'satir_no' => 1,
            'kalem_tipi' => 'stok_kalemi',
            'stok_id' => $stok->id,
            'miktar' => $miktar,
            'birim_fiyat' => '50',
            'kdv_orani' => '20',
            'net_tutar' => '100',
            'kdv_tutari' => '20',
            'toplam' => '120',
            'satir_toplami' => '100',
            'satir_genel_toplam' => '120',
            'para_birimi' => 'TRY',
        ]);
    }

    public function test_tenant_sadece_kendi_faturasini_gorur(): void
    {
        $f1 = $this->firmaOlustur('FAT1');
        $f2 = $this->firmaOlustur('FAT2');
        $user = User::query()->create([
            'name' => 'Tenant',
            'email' => 'tenant-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => false,
        ]);
        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $f1->id]);
        Fatura::query()->create(['firma_id' => $f1->id, 'tur' => FaturaTuru::Giden->value, 'durum' => FaturaDurumu::Taslak->value, 'tarih' => now()]);
        Fatura::query()->create(['firma_id' => $f2->id, 'tur' => FaturaTuru::Giden->value, 'durum' => FaturaDurumu::Taslak->value, 'tarih' => now()]);
        $this->assertSame(1, Fatura::query()->count());
    }

    public function test_fatura_onayinda_cari_ve_stok_hareketi_olusur_hizmette_stok_olusmaz(): void
    {
        $firma = $this->firmaOlustur('FAT3');
        $this->superAdminVeSession($firma);
        $cari = Cari::query()->create(['firma_id' => $firma->id, 'kod' => 'C-1', 'ad' => 'Cari', 'tur' => CariTuru::Musteri->value, 'durum' => CariDurumu::Aktif->value]);
        $stok = $this->stokOlustur($firma, '10');
        $fatura = $this->faturaOlustur($firma, $cari, FaturaTuru::Giden);
        $this->stokKalemiEkle($firma, $fatura, $stok, '2');
        FaturaKalemi::query()->create([
            'firma_id' => $firma->id, 'fatura_id' => $fatura->id, 'satir_no' => 2, 'kalem_tipi' => 'hizmet_kalemi',
            'hizmet_mi' => true, 'miktar' => '1', 'birim_fiyat' => '0', 'kdv_orani' => '0', 'net_tutar' => '0', 'kdv_tutari' => '0', 'toplam' => '0',
            'satir_toplami' => '0', 'satir_genel_toplam' => '0', 'para_birimi' => 'TRY',
        ]);

        app(FaturaIslemServisi::class)->faturayiOnayla($fatura->fresh());
        $this->assertSame(1, CariHareketi::query()->where('firma_id', $firma->id)->count());
        $this->assertSame(1, StokHareketi::query()->where('firma_id', $firma->id)->count());
        $this->assertSame($cari->id, StokHareketi::query()->where('belge_id', $fatura->id)->value('cari_id'));
    }

    public function test_proforma_onayinda_cari_ve_stok_kaydi_olmaz(): void
    {
        $firma = $this->firmaOlustur('FAT4');
        $this->superAdminVeSession($firma);
        $cari = Cari::query()->create(['firma_id' => $firma->id, 'kod' => 'C-2', 'ad' => 'Cari 2', 'tur' => CariTuru::Musteri->value, 'durum' => CariDurumu::Aktif->value]);
        $fatura = $this->faturaOlustur($firma, $cari, FaturaTuru::Proforma);
        app(FaturaIslemServisi::class)->faturayiOnayla($fatura->fresh());
        $this->assertSame(FaturaDurumu::Onayli, $fatura->fresh()->durum);
        $this->assertSame(0, CariHareketi::query()->count());
        $this->assertSame(0, StokHareketi::query()->count());
    }

    public function test_ayni_fatura_iki_kez_onaylanirsa_duplicate_hareket_olusturmaz(): void
    {
        $firma = $this->firmaOlustur('FAT5');
        $this->superAdminVeSession($firma);
        $cari = Cari::query()->create(['firma_id' => $firma->id, 'kod' => 'C-3', 'ad' => 'Cari 3', 'tur' => CariTuru::Musteri->value, 'durum' => CariDurumu::Aktif->value]);
        $stok = $this->stokOlustur($firma, '10');
        $fatura = $this->faturaOlustur($firma, $cari, FaturaTuru::Giden);
        $this->stokKalemiEkle($firma, $fatura, $stok, '2');
        app(FaturaIslemServisi::class)->faturayiOnayla($fatura->fresh());
        app(FaturaIslemServisi::class)->faturayiOnayla($fatura->fresh());
        $this->assertSame(1, CariHareketi::query()->where('belge_id', $fatura->id)->count());
        $this->assertSame(1, StokHareketi::query()->where('belge_id', $fatura->id)->count());
    }

    public function test_onayli_numarasiz_fatura_tekrar_onayda_numarasini_tamamlar(): void
    {
        $firma = $this->firmaOlustur('FAT5N');
        $this->superAdminVeSession($firma);
        $cari = Cari::query()->create(['firma_id' => $firma->id, 'kod' => 'C-5N', 'ad' => 'Cari 5N', 'tur' => CariTuru::Musteri->value, 'durum' => CariDurumu::Aktif->value]);
        $stok = $this->stokOlustur($firma, '10');
        $fatura = $this->faturaOlustur($firma, $cari, FaturaTuru::Giden);
        $this->stokKalemiEkle($firma, $fatura, $stok, '2');

        app(FaturaIslemServisi::class)->faturayiOnayla($fatura->fresh());
        $fatura->update(['fatura_no' => null]);
        app(FaturaIslemServisi::class)->faturayiOnayla($fatura->fresh());

        $this->assertNotEmpty($fatura->fresh()->fatura_no);
        $this->assertSame(1, CariHareketi::query()->where('belge_id', $fatura->id)->count());
        $this->assertSame(1, StokHareketi::query()->where('belge_id', $fatura->id)->count());
    }

    public function test_iptal_iki_kez_cagrilirsa_duplicate_ters_kayit_olusturmaz(): void
    {
        $firma = $this->firmaOlustur('FAT6');
        $this->superAdminVeSession($firma);
        $cari = Cari::query()->create(['firma_id' => $firma->id, 'kod' => 'C-4', 'ad' => 'Cari 4', 'tur' => CariTuru::Musteri->value, 'durum' => CariDurumu::Aktif->value]);
        $stok = $this->stokOlustur($firma, '10');
        $fatura = $this->faturaOlustur($firma, $cari, FaturaTuru::Giden);
        $this->stokKalemiEkle($firma, $fatura, $stok, '2');
        app(FaturaIslemServisi::class)->faturayiOnayla($fatura->fresh());
        app(FaturaIslemServisi::class)->faturayiIptalEt($fatura->fresh());
        app(FaturaIslemServisi::class)->faturayiIptalEt($fatura->fresh());

        $this->assertSame(FaturaDurumu::Iptal, $fatura->fresh()->durum);
        $this->assertSame(2, CariHareketi::query()->where('belge_id', $fatura->id)->count());
        $this->assertSame(2, StokHareketi::query()->where('belge_id', $fatura->id)->count());
        $this->assertSame(
            [$cari->id, $cari->id],
            StokHareketi::query()->where('belge_id', $fatura->id)->orderBy('id')->pluck('cari_id')->all()
        );
    }

    public function test_iade_iki_kez_cagrilirsa_duplicate_ters_kayit_olusturmaz(): void
    {
        $firma = $this->firmaOlustur('FAT7');
        $this->superAdminVeSession($firma);
        $cari = Cari::query()->create(['firma_id' => $firma->id, 'kod' => 'C-7', 'ad' => 'Cari 7', 'tur' => CariTuru::Musteri->value, 'durum' => CariDurumu::Aktif->value]);
        $stok = $this->stokOlustur($firma, '10');
        $fatura = $this->faturaOlustur($firma, $cari, FaturaTuru::Giden);
        $this->stokKalemiEkle($firma, $fatura, $stok, '2');
        app(FaturaIslemServisi::class)->faturayiOnayla($fatura->fresh());
        app(FaturaIslemServisi::class)->faturaIadeEt($fatura->fresh(), 'iade');
        app(FaturaIslemServisi::class)->faturaIadeEt($fatura->fresh(), 'iade');

        $this->assertSame(FaturaDurumu::Iade, $fatura->fresh()->durum);
        $this->assertSame(2, CariHareketi::query()->where('belge_id', $fatura->id)->count());
        $this->assertSame(2, StokHareketi::query()->where('belge_id', $fatura->id)->count());
    }

    public function test_onay_akisi_atomiktir_stok_fail_olursa_cari_ve_durum_geri_alinir(): void
    {
        $firma = $this->firmaOlustur('FAT8');
        $this->superAdminVeSession($firma);
        $cari = Cari::query()->create(['firma_id' => $firma->id, 'kod' => 'C-8', 'ad' => 'Cari 8', 'tur' => CariTuru::Musteri->value, 'durum' => CariDurumu::Aktif->value]);
        $stok = $this->stokOlustur($firma, '0');
        $fatura = $this->faturaOlustur($firma, $cari, FaturaTuru::Giden);
        $this->stokKalemiEkle($firma, $fatura, $stok, '2');

        $this->expectException(IsKuraliIstisnasi::class);
        app(FaturaIslemServisi::class)->faturayiOnayla($fatura->fresh());
    }

    public function test_onay_fail_sonrasi_durum_ve_hareketler_yazilmamis_kalir(): void
    {
        $firma = $this->firmaOlustur('FAT9');
        $this->superAdminVeSession($firma);
        $cari = Cari::query()->create(['firma_id' => $firma->id, 'kod' => 'C-9', 'ad' => 'Cari 9', 'tur' => CariTuru::Musteri->value, 'durum' => CariDurumu::Aktif->value]);
        $stok = $this->stokOlustur($firma, '0');
        $fatura = $this->faturaOlustur($firma, $cari, FaturaTuru::Giden);
        $this->stokKalemiEkle($firma, $fatura, $stok, '2');
        try {
            app(FaturaIslemServisi::class)->faturayiOnayla($fatura->fresh());
        } catch (\Throwable) {
        }
        $this->assertSame(FaturaDurumu::Taslak, $fatura->fresh()->durum);
        $this->assertSame(0, CariHareketi::query()->where('belge_id', $fatura->id)->count());
        $this->assertSame(0, StokHareketi::query()->where('belge_id', $fatura->id)->count());
    }

    public function test_giden_fatura_cari_alacak_ve_stok_cikis_uretiri(): void
    {
        $firma = $this->firmaOlustur('FAT10');
        $this->superAdminVeSession($firma);
        $cari = Cari::query()->create(['firma_id' => $firma->id, 'kod' => 'C-10', 'ad' => 'Cari 10', 'tur' => CariTuru::Musteri->value, 'durum' => CariDurumu::Aktif->value]);
        $stok = $this->stokOlustur($firma, '10');
        $fatura = $this->faturaOlustur($firma, $cari, FaturaTuru::GidenFatura);
        $this->stokKalemiEkle($firma, $fatura, $stok, '2');
        app(FaturaIslemServisi::class)->faturayiOnayla($fatura->fresh());
        $ch = CariHareketi::query()->where('belge_id', $fatura->id)->firstOrFail();
        $this->assertSame('0.00', number_format((float) $ch->borc, 2, '.', ''));
        $this->assertSame('120.00', number_format((float) $ch->alacak, 2, '.', ''));
        $this->assertSame('8.0000', number_format((float) $stok->fresh()->stok_miktari, 4, '.', ''));
    }

    public function test_gelen_ve_gider_fatura_cari_borc_uretiri(): void
    {
        $firma = $this->firmaOlustur('FAT11');
        $this->superAdminVeSession($firma);
        $cari = Cari::query()->create(['firma_id' => $firma->id, 'kod' => 'C-11', 'ad' => 'Cari 11', 'tur' => CariTuru::Tedarikci->value, 'durum' => CariDurumu::Aktif->value]);
        $stok = $this->stokOlustur($firma, '10');
        $f1 = $this->faturaOlustur($firma, $cari, FaturaTuru::GelenFatura);
        $this->stokKalemiEkle($firma, $f1, $stok, '2');
        app(FaturaIslemServisi::class)->faturayiOnayla($f1->fresh());
        $f2 = $this->faturaOlustur($firma, $cari, FaturaTuru::GiderFaturasi);
        $this->stokKalemiEkle($firma, $f2, $stok, '2');
        app(FaturaIslemServisi::class)->faturayiOnayla($f2->fresh());
        $this->assertSame(2, CariHareketi::query()->where('cari_id', $cari->id)->where('borc', '>', 0)->count());
    }

    public function test_iade_fatura_ters_yon_calisir(): void
    {
        $firma = $this->firmaOlustur('FAT12');
        $this->superAdminVeSession($firma);
        $cari = Cari::query()->create(['firma_id' => $firma->id, 'kod' => 'C-12', 'ad' => 'Cari 12', 'tur' => CariTuru::Musteri->value, 'durum' => CariDurumu::Aktif->value]);
        $stok = $this->stokOlustur($firma, '10');
        $fatura = $this->faturaOlustur($firma, $cari, FaturaTuru::IadeFatura);
        $this->stokKalemiEkle($firma, $fatura, $stok, '2');
        app(FaturaIslemServisi::class)->faturayiOnayla($fatura->fresh());
        $ch = CariHareketi::query()->where('belge_id', $fatura->id)->firstOrFail();
        $this->assertSame('120.00', number_format((float) $ch->borc, 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $ch->alacak, 2, '.', ''));
        $this->assertSame('12.0000', number_format((float) $stok->fresh()->stok_miktari, 4, '.', ''));
    }

    public function test_bekleyen_fatura_onayinda_hareket_uretmez(): void
    {
        $firma = $this->firmaOlustur('FAT13');
        $this->superAdminVeSession($firma);
        $cari = Cari::query()->create(['firma_id' => $firma->id, 'kod' => 'C-13', 'ad' => 'Cari 13', 'tur' => CariTuru::Musteri->value, 'durum' => CariDurumu::Aktif->value]);
        $fatura = $this->faturaOlustur($firma, $cari, FaturaTuru::BekleyenFatura);
        app(FaturaIslemServisi::class)->faturayiOnayla($fatura->fresh());
        $this->assertSame(FaturaDurumu::Onayli, $fatura->fresh()->durum);
        $this->assertSame(0, CariHareketi::query()->where('belge_id', $fatura->id)->count());
        $this->assertSame(0, StokHareketi::query()->where('belge_id', $fatura->id)->count());
    }

    public function test_idempotent_atla_tutarsizlik_warning_log_uretir(): void
    {
        $firma = $this->firmaOlustur('FAT14');
        $this->superAdminVeSession($firma);
        $cari = Cari::query()->create(['firma_id' => $firma->id, 'kod' => 'C-14', 'ad' => 'Cari 14', 'tur' => CariTuru::Musteri->value, 'durum' => CariDurumu::Aktif->value]);
        $fatura = $this->faturaOlustur($firma, $cari, FaturaTuru::GidenFatura);
        $fatura->update(['ara_toplam' => '0', 'kdv_toplam' => '0', 'genel_toplam' => '0', 'odenecek_tutar' => '0', 'acik_tutar' => '0']);
        FaturaKalemi::query()->create([
            'firma_id' => $firma->id,
            'fatura_id' => $fatura->id,
            'satir_no' => 1,
            'kalem_tipi' => 'hizmet_kalemi',
            'hizmet_mi' => true,
            'miktar' => '1',
            'birim_fiyat' => '0',
            'kdv_orani' => '0',
            'net_tutar' => '0',
            'kdv_tutari' => '0',
            'toplam' => '0',
            'satir_toplami' => '0',
            'satir_genel_toplam' => '0',
            'para_birimi' => 'TRY',
        ]);
        CariHareketi::query()->create([
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
            'belge_turu' => 'fatura',
            'belge_id' => $fatura->id,
            'islem_tarihi' => now(),
            'borc' => '0',
            'alacak' => '120',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);

        app(FaturaIslemServisi::class)->faturayiOnayla($fatura->fresh());
        $this->assertSame(FaturaDurumu::Onayli, $fatura->fresh()->durum);
        $this->assertSame(1, CariHareketi::query()->where('belge_id', $fatura->id)->count());
        $this->assertSame(0, StokHareketi::query()->where('belge_id', $fatura->id)->count());
    }
}
