<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\PosHesabi;
use App\Models\Muhasebe\StokKarti;
use App\Models\User;
use App\Muhasebe\Enumlar\CariHareketBelgeTuru;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Enumlar\StokBelgeTuru;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Servisler\CariHareketServisi;
use App\Muhasebe\Servisler\FaturaIslemServisi;
use App\Muhasebe\Servisler\FaturaNumaraUreticiServisi;
use App\Muhasebe\Servisler\FinansHareketServisi;
use App\Services\TenantContextService;
use App\Support\FirmaMuhasebeGuvenlikYardimcisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MuhasebeCekirdekEntegrasyonTest extends TestCase
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

    private function cariOlustur(Firma $firma, string $kod): Cari
    {
        return Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => $kod.'-'.uniqid(),
            'ad' => 'Cari '.$kod,
            'tur' => 'musteri',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);
    }

    private function superAdmin(): User
    {
        return User::query()->create([
            'name' => 'SA',
            'email' => 'sa-'.uniqid().'@test.local',
            'password' => bcrypt('secret'),
            'super_admin_mi' => true,
        ]);
    }

    private function faturaSatirHazirla(Fatura $fatura, bool $stoklu = false, ?int $stokId = null): void
    {
        FaturaKalemi::query()->create([
            'fatura_id' => $fatura->id,
            'stok_id' => $stoklu ? $stokId : null,
            'hizmet_mi' => ! $stoklu,
            'aciklama' => 'k',
            'miktar' => '1',
            'birim_fiyat' => '100.00',
            'kdv_orani' => '18.00',
            'satir_indirim_tutari' => '0',
            'net_tutar' => '0',
            'kdv_tutari' => '0',
            'toplam' => '118.00',
        ]);
    }

    public function test_fatura_onay_ve_iptal(): void
    {
        $this->actingAs($this->superAdmin());
        $firma = $this->firmaOlustur('FO');
        $cari = $this->cariOlustur($firma, 'FC');

        $fatura = Fatura::query()->create([
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
            'tur' => FaturaTuru::Giden,
            'durum' => FaturaDurumu::Taslak,
            'fatura_no' => null,
            'tarih' => now(),
            'vade_tarihi' => null,
            'ara_toplam' => '100.00',
            'kdv_toplam' => '18.00',
            'genel_toplam' => '118.00',
            'odenecek_tutar' => '118.00',
            'genel_indirim_tutari' => '0',
            'kdv_dahil_fiyatlandirma_mi' => false,
            'para_birimi' => 'TRY',
            'aciklama' => null,
        ]);
        $this->faturaSatirHazirla($fatura);

        $servis = app(FaturaIslemServisi::class);
        $servis->faturayiOnayla($fatura->fresh());
        $this->assertSame(FaturaDurumu::Onayli, $fatura->fresh()->durum);

        $servis->faturayiIptalEt($fatura->fresh());
        $this->assertSame(FaturaDurumu::Iptal, $fatura->fresh()->durum);
    }

    public function test_cari_ve_stok_yonu_giden_fatura(): void
    {
        $this->actingAs($this->superAdmin());
        $firma = $this->firmaOlustur('CV');
        $cari = $this->cariOlustur($firma, 'CC');
        $stok = StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'S-'.uniqid(),
            'ad' => 'Ürün',
            'tur' => StokKartiTuru::TicariMal,
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
            'stok_takip' => true,
            'stok_miktari' => '50',
        ]);

        $fatura = Fatura::query()->create([
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
            'tur' => FaturaTuru::Giden,
            'durum' => FaturaDurumu::Taslak,
            'fatura_no' => null,
            'tarih' => now(),
            'vade_tarihi' => null,
            'ara_toplam' => '100.00',
            'kdv_toplam' => '18.00',
            'genel_toplam' => '118.00',
            'odenecek_tutar' => '118.00',
            'genel_indirim_tutari' => '0',
            'kdv_dahil_fiyatlandirma_mi' => false,
            'para_birimi' => 'TRY',
            'aciklama' => null,
        ]);
        FaturaKalemi::query()->create([
            'fatura_id' => $fatura->id,
            'stok_id' => $stok->id,
            'hizmet_mi' => false,
            'aciklama' => 'k',
            'miktar' => '1',
            'birim_fiyat' => '100.00',
            'kdv_orani' => '18.00',
            'satir_indirim_tutari' => '0',
            'net_tutar' => '0',
            'kdv_tutari' => '0',
            'toplam' => '118.00',
        ]);

        app(FaturaIslemServisi::class)->faturayiOnayla($fatura->fresh());

        $this->assertDatabaseHas('cari_hareketleri', [
            'firma_id' => $firma->id,
            'borc' => '0.00',
            'alacak' => '118.00',
        ]);

        $this->assertDatabaseHas('stok_hareketleri', [
            'firma_id' => $firma->id,
            'stok_id' => $stok->id,
            'islem_turu' => StokHareketIslemTuru::Satis->value,
            'belge_turu' => StokBelgeTuru::Fatura->value,
        ]);
    }

    public function test_satis_iadesi_ve_alis_iadesi_cari(): void
    {
        $this->actingAs($this->superAdmin());
        $firma = $this->firmaOlustur('IA');
        $cari = $this->cariOlustur($firma, 'IC');

        foreach ([FaturaTuru::SatisIadesi, FaturaTuru::AlisIadesi] as $tur) {
            $fatura = Fatura::query()->create([
                'firma_id' => $firma->id,
                'cari_id' => $cari->id,
                'tur' => $tur,
                'durum' => FaturaDurumu::Taslak,
                'fatura_no' => null,
                'tarih' => now(),
                'vade_tarihi' => null,
                'ara_toplam' => '100.00',
                'kdv_toplam' => '18.00',
                'genel_toplam' => '118.00',
                'odenecek_tutar' => '118.00',
                'genel_indirim_tutari' => '0',
                'kdv_dahil_fiyatlandirma_mi' => false,
                'para_birimi' => 'TRY',
                'aciklama' => null,
            ]);
            $this->faturaSatirHazirla($fatura);
            app(FaturaIslemServisi::class)->faturayiOnayla($fatura->fresh());
        }

        $this->assertDatabaseHas('cari_hareketleri', [
            'firma_id' => $firma->id,
            'borc' => '118.00',
            'alacak' => '0.00',
        ]);
        $this->assertDatabaseHas('cari_hareketleri', [
            'firma_id' => $firma->id,
            'borc' => '0.00',
            'alacak' => '118.00',
        ]);
    }

    public function test_kasa_tahsilat_ve_banka_odeme(): void
    {
        $u = $this->superAdmin();
        $this->actingAs($u);
        $firma = $this->firmaOlustur('KB');
        $cari = $this->cariOlustur($firma, 'KC');
        $kasa = KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'K-'.uniqid(),
            'ad' => 'Kasa',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);
        $banka = BankaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'B-'.uniqid(),
            'ad' => 'Banka',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);

        $fin = app(FinansHareketServisi::class);
        $fin->tahsilatKasadanKaydet((int) $firma->id, (int) $cari->id, (int) $kasa->id, '50.00', 'TRY', now(), 'kasa test');
        $fin->odemeBankadanKaydet(
            (int) $firma->id,
            (int) $cari->id,
            (int) $banka->id,
            '25.00',
            'TRY',
            now(),
            'banka ödeme',
            null,
            null,
            'D-1',
            'REF-9',
            'EFT açıklaması'
        );

        $this->assertDatabaseHas('finans_hareketleri', ['firma_id' => $firma->id, 'islem_yapan_kullanici_id' => $u->id]);
        $this->assertDatabaseHas('banka_hareketleri', ['dekont_no' => 'D-1', 'islem_referansi' => 'REF-9']);
    }

    public function test_pos_tahsilat_ve_komisyonlu(): void
    {
        $u = $this->superAdmin();
        $this->actingAs($u);
        $firma = $this->firmaOlustur('PS');
        $cari = $this->cariOlustur($firma, 'PC');
        $pos = PosHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'P-'.uniqid(),
            'ad' => 'POS',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);

        $fin = app(FinansHareketServisi::class);
        $fin->tahsilatPosKaydet(
            (int) $firma->id,
            (int) $cari->id,
            (int) $pos->id,
            '100.00',
            'TRY',
            now(),
            'pos düz',
            null,
            null,
            'SL-1',
            'PR-2',
            null
        );

        $fin->tahsilatPosKomisyonluKaydet(
            (int) $firma->id,
            (int) $cari->id,
            (int) $pos->id,
            '100.00',
            '2.50',
            'TRY',
            now(),
            'komisyonlu'
        );

        $this->assertDatabaseHas('pos_hareketleri', ['slip_no' => 'SL-1']);
        $this->assertDatabaseHas('finans_hareketleri', [
            'firma_id' => $firma->id,
            'pos_komisyon_tutari' => '2.50',
        ]);
    }

    public function test_tenant_baska_firmaya_yazamaz(): void
    {
        $user = User::query()->create([
            'name' => 'T',
            'email' => 't-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => false,
        ]);
        $fa = $this->firmaOlustur('A');
        $fb = $this->firmaOlustur('B');
        $cariB = $this->cariOlustur($fb, 'CB');

        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $fa->id]);

        $this->expectException(IsKuraliIstisnasi::class);
        app(CariHareketServisi::class)->kayitOlustur((int) $fb->id, [
            'cari_id' => (int) $cariB->id,
            'belge_turu' => CariHareketBelgeTuru::Tahsilat,
            'belge_id' => 1,
            'islem_tarihi' => now(),
            'borc' => '1',
            'alacak' => '0',
            'para_birimi' => 'TRY',
        ]);
    }

    public function test_fatura_numara_sirali_uretilir(): void
    {
        $this->actingAs($this->superAdmin());
        $firma = $this->firmaOlustur('NM');
        $yil = (int) now()->year;
        $u = app(FaturaNumaraUreticiServisi::class);
        $a = $u->sonrakiNumarayiUret((int) $firma->id, $yil);
        $b = $u->sonrakiNumarayiUret((int) $firma->id, $yil);
        $this->assertNotSame($a, $b);
        $this->assertStringContainsString((string) $yil, $a);
    }

    public function test_firma_silme_muhasebe_varsa_engellenir(): void
    {
        $this->actingAs($this->superAdmin());
        $firma = $this->firmaOlustur('SG');
        $this->cariOlustur($firma, 'X');
        $this->assertTrue(FirmaMuhasebeGuvenlikYardimcisi::muhasebeKaydiVarMi((int) $firma->id));
    }
}
