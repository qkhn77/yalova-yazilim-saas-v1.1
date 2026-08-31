<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\DovizKuru;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\Senet;
use App\Models\Muhasebe\SenetHareketi;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\User;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\SenetDurumu;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Servisler\SenetServisi;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SenetTakipTest extends TestCase
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
            'name' => 'Senet Test',
            'email' => 'senet-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => true,
        ]);
        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);
    }

    private function cariOlustur(Firma $firma, string $kod): Cari
    {
        return Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => $kod,
            'ad' => 'Cari '.$kod,
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
            'ad' => 'Senet Kasa',
            'para_birimi' => 'TRY',
            'durum' => HesapDurumu::Aktif,
        ]);
    }

    public function test_usd_senet_try_kasa_ile_tahsil_edilir_ve_baz_tutar_snapshoti_kapanis_kurunu_tutar(): void
    {
        config([
            'muhasebe.coklu_para_birimi.aktif' => true,
            'muhasebe.coklu_para_birimi.kur_donusumu_aktif' => true,
            'muhasebe.coklu_para_birimi.baz_para_birimi' => 'TRY',
        ]);

        $firma = $this->firmaOlustur('SN-USD');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, 'C-SN-USD');
        $kasa = $this->kasaOlustur($firma);

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

        $senet = app(SenetServisi::class)->girisKaydet($firma->id, [
            'cari_id' => $cari->id,
            'senet_no' => 'SNT-USD-001',
            'tutar' => '100.00',
            'para_birimi' => 'USD',
            'vade_tarihi' => '2026-08-15',
            'islem_tarihi' => '2026-07-20 10:00:00',
        ]);
        $this->assertSame('4000.00', (string) $senet->baz_tutar);

        $sonuc = app(SenetServisi::class)->tahsilatEkle($senet, [
            'kanal' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
            'tutar' => '100.00',
            'doviz_kuru' => '42',
            'hedef_tutar' => '4200.00',
            'islem_tarihi' => '2026-08-01 10:00:00',
            'kapanma_sekli' => 'odendi_iade',
        ]);

        $finans = FinansHareketi::query()->where('referans_turu', 'senet')->where('referans_id', $senet->id)->latest('id')->firstOrFail();
        $this->assertSame('USD', (string) $sonuc->para_birimi);
        $this->assertSame('4000.00', (string) $senet->fresh()->baz_tutar);
        $this->assertSame('4200.00', (string) $finans->baz_tutar);
        $this->assertSame('TRY', (string) $finans->baz_para_birimi);
        $this->assertSame('42.00000000', (string) $finans->kur);
        $this->assertDatabaseHas('kasa_hareketleri', [
            'finans_hareket_id' => $finans->id,
            'para_birimi' => 'TRY',
            'tutar' => '4200.00',
        ]);
    }

    public function test_senet_girisi_idempotenttir_ve_para_hareketi_olusturmaz(): void
    {
        $firma = $this->firmaOlustur('SN1');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, 'C-SN1');
        $veri = [
            'cari_id' => $cari->id,
            'senet_no' => 'SNT-001',
            'tutar' => '1500.00',
            'para_birimi' => 'TRY',
            'vade_tarihi' => '2026-08-15',
            'islem_tarihi' => '2026-07-20 10:00:00',
        ];

        $ilk = app(SenetServisi::class)->girisKaydet($firma->id, $veri);
        $ikinci = app(SenetServisi::class)->girisKaydet($firma->id, $veri);

        $this->assertSame($ilk->id, $ikinci->id);
        $this->assertSame(SenetDurumu::Portfoyde, $ilk->fresh()->durum);
        $this->assertDatabaseCount('senetler', 1);
        $this->assertDatabaseCount('senet_hareketleri', 1);
        $this->assertDatabaseCount('finans_hareketleri', 0);
        $this->assertDatabaseCount('kasa_hareketleri', 0);
        $this->assertDatabaseCount('banka_hareketleri', 0);
        $this->assertDatabaseCount('pos_hareketleri', 0);
    }

    public function test_senet_odemesi_tahsilat_ve_kasa_hareketi_olusturur_ve_idempotenttir(): void
    {
        $firma = $this->firmaOlustur('SN2');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, 'C-SN2');
        $kasa = $this->kasaOlustur($firma);
        $servis = app(SenetServisi::class);
        $senet = $servis->girisKaydet($firma->id, [
            'cari_id' => $cari->id,
            'senet_no' => 'SNT-002',
            'tutar' => '750.00',
            'para_birimi' => 'TRY',
            'vade_tarihi' => '2026-08-20',
            'islem_tarihi' => '2026-07-20 10:00:00',
        ]);
        $veri = [
            'kanal' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
            'tutar' => '750.00',
            'kapanma_sekli' => 'odendi_iade',
            'islem_tarihi' => '2026-07-20 11:00:00',
        ];

        $servis->tahsilatEkle($senet, $veri);
        $servis->tahsilatEkle($senet, $veri);

        $senet = $senet->fresh();
        $this->assertSame(SenetDurumu::Odendi, $senet->durum);
        $this->assertDatabaseCount('senet_hareketleri', 2);
        $this->assertDatabaseHas('senet_hareketleri', [
            'senet_id' => $senet->id,
            'islem_turu' => 'tahsilat',
            'finans_hareket_id' => $senet->odeme_finans_hareket_id,
        ]);
        $this->assertDatabaseCount('finans_hareketleri', 1);
        $this->assertDatabaseCount('kasa_hareketleri', 1);
        $this->assertDatabaseHas('finans_hareketleri', [
            'referans_turu' => 'senet',
            'referans_id' => $senet->id,
            'tur' => 'tahsilat',
            'cari_id' => $cari->id,
        ]);

        $gecmis = view('filament.clusters.muhasebe.pages.senet-hareket-gecmisi', [
            'senet' => $senet->load([
                'hareketleri.cari',
                'hareketleri.islemYapanKullanici',
                'hareketleri.finansHareketi.kasaHareketleri.kasaHesabi',
            ]),
        ])->render();

        $this->assertStringContainsString('Kasa — Senet Kasa', $gecmis);
    }

    public function test_senet_iade_ile_kapatilinca_finans_hareketi_olusturmaz(): void
    {
        $firma = $this->firmaOlustur('SN3');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, 'C-SN3');
        $senet = app(SenetServisi::class)->girisKaydet($firma->id, [
            'cari_id' => $cari->id,
            'senet_no' => 'SNT-003',
            'tutar' => '400.00',
            'para_birimi' => 'TRY',
            'vade_tarihi' => '2026-08-25',
            'islem_tarihi' => '2026-07-20 12:00:00',
        ]);

        app(SenetServisi::class)->odemesizKapat($senet, [
            'kapanma_sekli' => 'iade_edildi',
            'islem_tarihi' => '2026-07-20 13:00:00',
            'aciklama' => 'Ödeme sonrası müşteriye iade edildi',
        ]);

        $this->assertSame(SenetDurumu::IadeEdildi, $senet->fresh()->durum);
        $this->assertDatabaseCount('senet_hareketleri', 2);
        $this->assertDatabaseCount('finans_hareketleri', 0);
        $this->assertDatabaseCount('kasa_hareketleri', 0);
    }

    public function test_kendi_senedi_odeme_hareketi_olusturur(): void
    {
        $firma = $this->firmaOlustur('SN4');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, 'C-SN4');
        $kasa = $this->kasaOlustur($firma);
        $senet = app(SenetServisi::class)->cikisKaydet($firma->id, [
            'kaynak' => 'kendi',
            'cari_id' => $cari->id,
            'senet_no' => 'SNT-004',
            'tutar' => '900.00',
            'para_birimi' => 'TRY',
            'vade_tarihi' => '2026-08-30',
            'islem_tarihi' => '2026-07-20 14:00:00',
        ]);

        app(SenetServisi::class)->odemeYap($senet, [
            'kanal' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
            'tutar' => '900.00',
            'kapanma_sekli' => 'odendi_imha',
            'islem_tarihi' => '2026-07-20 15:00:00',
        ]);

        $senet = $senet->fresh();
        $this->assertSame(SenetDurumu::ImhaEdildi, $senet->durum);
        $this->assertDatabaseHas('finans_hareketleri', [
            'referans_turu' => 'senet',
            'referans_id' => $senet->id,
            'tur' => 'odeme',
        ]);
        $this->assertDatabaseHas('kasa_hareketleri', [
            'finans_hareket_id' => $senet->odeme_finans_hareket_id,
            'tutar' => '-900.00',
        ]);
    }

    public function test_normal_kullanici_baska_firmaya_senet_yazamaz(): void
    {
        $firma = $this->firmaOlustur('SN5');
        $baskaFirma = $this->firmaOlustur('SN6');
        $user = User::query()->create([
            'name' => 'Kiracı',
            'email' => 'senet-kiraci-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => false,
        ]);
        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);
        $cari = $this->cariOlustur($baskaFirma, 'C-SN6');

        $this->expectException(IsKuraliIstisnasi::class);
        app(SenetServisi::class)->girisKaydet($baskaFirma->id, [
            'cari_id' => $cari->id,
            'senet_no' => 'SNT-005',
            'tutar' => '100.00',
            'para_birimi' => 'TRY',
            'vade_tarihi' => '2026-08-25',
            'islem_tarihi' => '2026-07-20 12:00:00',
        ]);
    }

    public function test_iptal_edilen_tahsilat_duzeltilip_yeniden_kaydedilebilir(): void
    {
        $firma = $this->firmaOlustur('SN7');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, 'C-SN7');
        $kasa = $this->kasaOlustur($firma);
        $servis = app(SenetServisi::class);
        $senet = $servis->girisKaydet($firma->id, [
            'cari_id' => $cari->id,
            'senet_no' => 'SNT-007',
            'tutar' => '250.00',
            'para_birimi' => 'TRY',
            'vade_tarihi' => '2026-08-25',
            'islem_tarihi' => '2026-07-20 10:00:00',
        ]);
        $ilkVeri = [
            'kanal' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
            'tutar' => '250.00',
            'kapanma_sekli' => 'odendi_iade',
            'islem_tarihi' => '2026-07-20 11:00:00',
        ];

        $servis->tahsilatEkle($senet, $ilkVeri);
        $servis->iptalEt($senet->fresh());
        $yeniden = $servis->tahsilatEkle($senet->fresh(), [
            ...$ilkVeri,
            'islem_tarihi' => '2026-07-20 12:00:00',
        ]);

        $this->assertSame(SenetDurumu::Odendi, $yeniden->fresh()->durum);
        $this->assertDatabaseCount('finans_hareketleri', 3);
        $this->assertDatabaseCount('senet_hareketleri', 3);
        $this->assertDatabaseHas('senet_hareketleri', [
            'senet_id' => $senet->id,
            'islem_turu' => 'tahsilat',
            'durum' => 'aktif',
            'aciklama' => 'Senet tahsilatı: SNT-007',
        ]);
    }
}
