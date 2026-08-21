<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\BankaHareketi;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\KasaHareketi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\PosHareketi;
use App\Models\Muhasebe\PosHesabi;
use App\Models\User;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Enumlar\FinansHareketDurumu;
use App\Muhasebe\Enumlar\HareketDurumu;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\PosTipi;
use App\Muhasebe\Enumlar\SaglayiciTipi;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Servisler\CariBakiyeServisi;
use App\Muhasebe\Servisler\FaturaFinansKapamaServisi;
use App\Muhasebe\Servisler\FaturaIslemServisi;
use App\Muhasebe\Servisler\FaturaNumaraUreticiServisi;
use App\Muhasebe\Servisler\FaturaToplamDogrulamaServisi;
use App\Muhasebe\Servisler\FinansHareketServisi;
use App\Muhasebe\Servisler\KasaSilmeServisi;
use App\Services\MuhasebeDisaAktarimServisi;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MuhasebeFinalHardeningTest extends TestCase
{
    use RefreshDatabase;

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

    private function firma(string $suffix = 'F'): Firma
    {
        return Firma::query()->create([
            'ad' => 'Final '.$suffix,
            'kisa_ad' => 'FF-'.$suffix,
            'firma_kodu' => 'FFK-'.$suffix.'-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
    }

    private function cari(Firma $firma, string $pb = 'TRY'): Cari
    {
        return Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'C-'.uniqid(),
            'ad' => 'Cari '.uniqid(),
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => $pb,
        ]);
    }

    private function kasa(Firma $firma, string $pb = 'TRY'): KasaHesabi
    {
        return KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'K-'.uniqid(),
            'ad' => 'Kasa',
            'para_birimi' => $pb,
            'durum' => HesapDurumu::Aktif->value,
        ]);
    }

    private function banka(Firma $firma, string $pb = 'TRY'): BankaHesabi
    {
        return BankaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'B-'.uniqid(),
            'ad' => 'Banka',
            'banka_adi' => 'Test Bank',
            'para_birimi' => $pb,
            'durum' => HesapDurumu::Aktif->value,
        ]);
    }

    private function pos(Firma $firma, string $pb = 'TRY'): PosHesabi
    {
        return PosHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'P-'.uniqid(),
            'ad' => 'POS',
            'pos_tipi' => PosTipi::FizikiPos->value,
            'saglayici_tipi' => SaglayiciTipi::BankaPosu->value,
            'banka_adi' => 'Test Bank',
            'saglayici_adi' => 'Test POS',
            'para_birimi' => $pb,
            'durum' => HesapDurumu::Aktif->value,
        ]);
    }

    private function fatura(Firma $firma, Cari $cari, array $override = []): Fatura
    {
        $f = Fatura::query()->create(array_merge([
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
            'tur' => FaturaTuru::Giden->value,
            'durum' => FaturaDurumu::Taslak->value,
            'tarih' => now(),
            'para_birimi' => 'TRY',
            'ara_toplam' => '100.00',
            'toplam_indirim' => '0.00',
            'genel_indirim_tutari' => '0.00',
            'kdv_toplam' => '20.00',
            'tevkifat_orani' => '0.00',
            'genel_toplam' => '120.00',
            'odenecek_tutar' => '120.00',
            'odendi_tutari' => '0.00',
            'acik_tutar' => '120.00',
            'odeme_durumu' => 'odenmedi',
            'kdv_dahil_fiyatlandirma_mi' => false,
            'doviz_kuru' => '1.00000000',
        ], $override));

        FaturaKalemi::query()->create([
            'firma_id' => $firma->id,
            'fatura_id' => $f->id,
            'satir_no' => 1,
            'kalem_tipi' => 'hizmet_kalemi',
            'hizmet_mi' => true,
            'miktar' => '1.0000',
            'birim_fiyat' => (string) ($override['kalem_birim_fiyat'] ?? '100.00'),
            'indirim_orani' => (string) ($override['kalem_indirim_orani'] ?? '0.00'),
            'kdv_orani' => (string) ($override['kalem_kdv_orani'] ?? '20.00'),
            'satir_indirim_tutari' => (string) ($override['kalem_satir_indirim_tutari'] ?? '0.00'),
            'indirim_tutari' => (string) ($override['kalem_satir_indirim_tutari'] ?? '0.00'),
            'net_tutar' => (string) ($override['kalem_net_tutar'] ?? '100.00'),
            'kdv_tutari' => (string) ($override['kalem_kdv_tutari'] ?? '20.00'),
            'satir_toplami' => (string) ($override['kalem_net_tutar'] ?? '100.00'),
            'satir_genel_toplam' => (string) ($override['kalem_toplam'] ?? '120.00'),
            'toplam' => (string) ($override['kalem_toplam'] ?? '120.00'),
            'para_birimi' => (string) ($override['para_birimi'] ?? 'TRY'),
        ]);

        return $f;
    }

    public function test_kdv_haric_hesap_dogrulanir(): void
    {
        $firma = $this->firma('K1');
        $this->superAdminVeSession($firma);
        $cari = $this->cari($firma);
        $f = $this->fatura($firma, $cari);

        app(FaturaToplamDogrulamaServisi::class)->dogrula($f);
        $this->assertTrue(true);
    }

    public function test_kdv_dahil_hesap_dogrulanir(): void
    {
        $firma = $this->firma('K2');
        $this->superAdminVeSession($firma);
        $cari = $this->cari($firma);
        $f = $this->fatura($firma, $cari, [
            'kdv_dahil_fiyatlandirma_mi' => true,
            'ara_toplam' => '100.00',
            'kdv_toplam' => '20.00',
            'genel_toplam' => '120.00',
            'odenecek_tutar' => '120.00',
            'kalem_birim_fiyat' => '120.00',
            'kalem_net_tutar' => '100.00',
            'kalem_kdv_tutari' => '20.00',
            'kalem_toplam' => '120.00',
        ]);

        app(FaturaToplamDogrulamaServisi::class)->dogrula($f);
        $this->assertTrue(true);
    }

    public function test_iskonto_ve_tevkifat_hesabi_dogrulanir(): void
    {
        $firma = $this->firma('K3');
        $this->superAdminVeSession($firma);
        $cari = $this->cari($firma);
        $f = $this->fatura($firma, $cari, [
            'ara_toplam' => '90.00',
            'kdv_toplam' => '18.00',
            'genel_toplam' => '108.00',
            'tevkifat_orani' => '50.00',
            'odenecek_tutar' => '99.00',
            'kalem_birim_fiyat' => '100.00',
            'kalem_satir_indirim_tutari' => '10.00',
            'kalem_net_tutar' => '90.00',
            'kalem_kdv_tutari' => '18.00',
            'kalem_toplam' => '108.00',
        ]);

        app(FaturaToplamDogrulamaServisi::class)->dogrula($f);
        $this->assertTrue(true);
    }

    public function test_tevkifat_hatali_odenecek_tutar_reddedilir(): void
    {
        $firma = $this->firma('K4');
        $this->superAdminVeSession($firma);
        $cari = $this->cari($firma);
        $f = $this->fatura($firma, $cari, [
            'tevkifat_orani' => '50.00',
            'odenecek_tutar' => '120.00',
        ]);

        $this->expectException(IsKuraliIstisnasi::class);
        app(FaturaToplamDogrulamaServisi::class)->dogrula($f);
    }

    public function test_farkli_para_birimi_kapama_reddedilir(): void
    {
        $firma = $this->firma('PB1');
        $this->superAdminVeSession($firma);
        $cariUsd = $this->cari($firma, 'USD');
        $kasaUsd = $this->kasa($firma, 'USD');
        $cariTry = $this->cari($firma, 'TRY');
        $f = $this->fatura($firma, $cariTry, ['para_birimi' => 'TRY']);
        app(FaturaIslemServisi::class)->faturayiOnayla($f);

        $finans = app(FinansHareketServisi::class)->tahsilatKasadanKaydet(
            $firma->id,
            $cariUsd->id,
            $kasaUsd->id,
            '50.00',
            'USD',
            now(),
            'usd tahsilat'
        )['finans'];

        $this->expectException(IsKuraliIstisnasi::class);
        app(FaturaFinansKapamaServisi::class)->finansiFaturalaraDagit($finans, [
            ['fatura_id' => $f->id, 'tutar' => '10.00'],
        ]);
    }

    public function test_ters_kayit_idempotent_duplicate_uretmez(): void
    {
        $firma = $this->firma('TRS');
        $this->superAdminVeSession($firma);
        $cari = $this->cari($firma);
        $kasa = $this->kasa($firma);

        $finans = app(FinansHareketServisi::class)->tahsilatKasadanKaydet(
            $firma->id,
            $cari->id,
            $kasa->id,
            '100.00',
            'TRY',
            now(),
            'test'
        )['finans'];

        $ilk = app(FinansHareketServisi::class)->tersKayitOlustur($finans);
        $ikinci = app(FinansHareketServisi::class)->tersKayitOlustur($finans);

        $this->assertSame((int) $ilk->id, (int) $ikinci->id);
        $this->assertSame(1, FinansHareketi::query()
            ->where('iptal_edilen_hareket_id', $finans->id)
            ->where('durum', FinansHareketDurumu::Aktif)
            ->count());
    }

    public function test_ters_kayit_cari_ve_kasa_bakiyesini_net_sifirlar(): void
    {
        $firma = $this->firma('REV-BAL');
        $this->superAdminVeSession($firma);
        $cari = $this->cari($firma);
        $kasa = $this->kasa($firma);

        $onceCari = (string) (app(CariBakiyeServisi::class)
            ->paraBirimiOzetleri($firma->id)
            ->firstWhere('para_birimi', 'TRY')?->bakiye ?? '0.00');
        $onceKasa = (string) KasaHareketi::query()
            ->where('kasa_hesap_id', $kasa->id)
            ->where('durum', HareketDurumu::Aktif)
            ->sum('tutar');

        $finans = app(FinansHareketServisi::class)->tahsilatKasadanKaydet(
            $firma->id,
            $cari->id,
            $kasa->id,
            '100.00',
            'TRY',
            now(),
            'net reversal'
        )['finans'];

        app(FinansHareketServisi::class)->tersKayitOlustur($finans);

        $sonraCari = (string) (app(CariBakiyeServisi::class)
            ->paraBirimiOzetleri($firma->id)
            ->firstWhere('para_birimi', 'TRY')?->bakiye ?? '0.00');
        $sonraKasa = (string) KasaHareketi::query()
            ->where('kasa_hesap_id', $kasa->id)
            ->where('durum', HareketDurumu::Aktif)
            ->sum('tutar');

        $this->assertSame($onceCari, $sonraCari);
        $this->assertSame($onceKasa, $sonraKasa);
        $this->assertSame(0, KasaHareketi::query()
            ->where('finans_hareket_id', $finans->id)
            ->where('durum', HareketDurumu::Aktif)
            ->count());
    }

    public function test_cari_bakiye_para_birimi_bazinda_dogru_hesaplanir(): void
    {
        $firma = $this->firma('CB');
        $this->superAdminVeSession($firma);
        $cariTry = $this->cari($firma, 'TRY');
        $cariUsd = $this->cari($firma, 'USD');
        $kasaTry = $this->kasa($firma, 'TRY');
        $kasaUsd = $this->kasa($firma, 'USD');

        app(FinansHareketServisi::class)->tahsilatKasadanKaydet($firma->id, $cariTry->id, $kasaTry->id, '100.00', 'TRY', now(), 't1');
        app(FinansHareketServisi::class)->odemeKasadanKaydet($firma->id, $cariTry->id, $kasaTry->id, '40.00', 'TRY', now(), 'o1');
        app(FinansHareketServisi::class)->tahsilatKasadanKaydet($firma->id, $cariUsd->id, $kasaUsd->id, '50.00', 'USD', now(), 't2');

        $ozet = app(CariBakiyeServisi::class)->paraBirimiOzetleri($firma->id);
        $try = $ozet->firstWhere('para_birimi', 'TRY');
        $usd = $ozet->firstWhere('para_birimi', 'USD');

        $this->assertSame('60.00', (string) $try->bakiye);
        $this->assertSame('50.00', (string) $usd->bakiye);
    }

    public function test_finans_kasa_banka_pos_tutarliligi_ve_export_verisi_dogrulanir(): void
    {
        $firma = $this->firma('EXP');
        $this->superAdminVeSession($firma);
        $cari = $this->cari($firma, 'TRY');
        $kasa = $this->kasa($firma, 'TRY');
        $banka = $this->banka($firma, 'TRY');
        $pos = $this->pos($firma, 'TRY');

        app(FinansHareketServisi::class)->tahsilatKasadanKaydet($firma->id, $cari->id, $kasa->id, '20.00', 'TRY', now(), 'kasa');
        app(FinansHareketServisi::class)->odemeBankadanKaydet($firma->id, $cari->id, $banka->id, '5.00', 'TRY', now(), 'banka');
        app(FinansHareketServisi::class)->tahsilatPosKaydet($firma->id, $cari->id, $pos->id, '7.00', 'TRY', now(), 'pos');

        $f = $this->fatura($firma, $cari, ['fatura_no' => null]);
        app(FaturaIslemServisi::class)->faturayiOnayla($f);
        $f->refresh();

        $this->assertNotEmpty((string) $f->fatura_no);
        $this->assertSame(1, KasaHareketi::query()->where('kasa_hesap_id', $kasa->id)->count());
        $this->assertSame(1, BankaHareketi::query()->where('banka_hesap_id', $banka->id)->count());
        $this->assertSame(1, PosHareketi::query()->where('pos_hesap_id', $pos->id)->count());

        $export = app(MuhasebeDisaAktarimServisi::class)->faturaListesi($firma->id, now()->subDay(), now()->addDay());
        $this->assertNotEmpty($export);
        $this->assertArrayHasKey('Fatura No', $export[0]);
        $this->assertArrayHasKey('KDV Toplam', $export[0]);
    }

    public function test_kasa_silinirken_kayitlar_masaustu_kasasina_tasinir(): void
    {
        $firma = $this->firma('KASA-SIL');
        $this->superAdminVeSession($firma);
        $cari = $this->cari($firma);
        $kasa = $this->kasa($firma);

        app(FinansHareketServisi::class)->tahsilatKasadanKaydet(
            $firma->id,
            $cari->id,
            $kasa->id,
            '125.00',
            'TRY',
            now(),
            'silme testi',
        );

        $sonuc = app(KasaSilmeServisi::class)->tasiyarakSil($kasa);
        $hedef = KasaHesabi::withTrashed()
            ->where('firma_id', $firma->id)
            ->where('ad', 'Masaüstü')
            ->where('para_birimi', 'TRY')
            ->firstOrFail();

        $this->assertNotEmpty($sonuc['hedefler']);
        $this->assertSoftDeleted('kasa_hesaplari', ['id' => $kasa->id]);
        $this->assertSame(1, KasaHareketi::query()->where('kasa_hesap_id', $hedef->id)->count());
        $this->assertSame(0, KasaHareketi::query()->where('kasa_hesap_id', $kasa->id)->count());
    }

    public function test_fatura_numara_uretimi_firma_bazinda_bagimsiz_artar(): void
    {
        $f1 = $this->firma('N1');
        $f2 = $this->firma('N2');
        $this->superAdminVeSession($f1);

        $servis = app(FaturaNumaraUreticiServisi::class);
        $n11 = $servis->sonrakiNumarayiUret($f1->id, 2026);
        $n12 = $servis->sonrakiNumarayiUret($f1->id, 2026);
        $n21 = $servis->sonrakiNumarayiUret($f2->id, 2026);

        $this->assertSame('2026-000001', $n11);
        $this->assertSame('2026-000002', $n12);
        $this->assertSame('2026-000001', $n21);
    }
}
