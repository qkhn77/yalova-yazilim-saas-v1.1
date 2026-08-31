<?php

namespace Tests\Feature\Muhasebe;

use App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\BarkodEtiketYazdirmaSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisFisiSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\HizliSatisSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisAyarlarSayfasi;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\KasaHareketi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\PosHareketi;
use App\Models\Muhasebe\PosHesabi;
use App\Models\Muhasebe\StokBarkodu;
use App\Models\Muhasebe\StokHareketi;
use App\Models\Muhasebe\StokKategorisi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\VergiOrani;
use App\Models\User;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\FinansHareketDurumu;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokBelgeTuru;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Muhasebe\Servisler\BarkodluSatisAlacakOzetServisi;
use App\Muhasebe\Servisler\BarkodluSatisServisi;
use App\Services\FirmaAyarDeposu;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BarkodluSatisTahsilatVeFisTest extends TestCase
{
    use RefreshDatabase;

    public function test_nakit_satis_tahsilat_kaydi_olusturur(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('NAKIT');
        $stok = $this->stokOlustur($firma, ['stok_miktari' => '10.0000']);
        $this->kasaOlustur($firma);

        $satis = app(BarkodluSatisServisi::class)->satisTamamla((int) $firma->id, (int) $user->id, [
            'satis_tarihi' => now()->toDateTimeString(),
            'odeme_tipi' => 'nakit',
            'para_birimi' => 'TRY',
            'kalemler' => [[
                'stok_id' => (int) $stok->id,
                'miktar' => 1,
                'birim_fiyat' => 100,
                'kdv_orani' => 20,
            ]],
        ]);

        $finans = FinansHareketi::query()
            ->where('firma_id', (int) $firma->id)
            ->where('referans_turu', 'barkodlu_satis')
            ->where('referans_id', (int) $satis->id)
            ->where('durum', FinansHareketDurumu::Aktif->value)
            ->first();

        $this->assertNotNull($finans);
        $this->assertDatabaseHas('kasa_hareketleri', [
            'finans_hareket_id' => (int) $finans->id,
            'firma_id' => (int) $firma->id,
            'para_birimi' => 'TRY',
        ]);
    }

    public function test_cari_secimsiz_satis_perakende_cariye_baglanir(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('PERAKENDE-CARI');
        $stok = $this->stokOlustur($firma, ['stok_miktari' => '10.0000']);
        $this->kasaOlustur($firma);

        $satis = app(BarkodluSatisServisi::class)->satisTamamla((int) $firma->id, (int) $user->id, [
            'satis_tarihi' => now()->toDateTimeString(),
            'odeme_tipi' => 'nakit',
            'para_birimi' => 'TRY',
            'kalemler' => [[
                'stok_id' => (int) $stok->id,
                'miktar' => 1,
                'birim_fiyat' => 100,
                'kdv_orani' => 20,
            ]],
        ]);

        $satis->refresh();
        $this->assertNotNull($satis->cari_id);
        $this->assertDatabaseHas('cariler', [
            'id' => (int) $satis->cari_id,
            'firma_id' => (int) $firma->id,
            'kod' => 'PERAKENDE-MUSTERI',
            'ad' => 'Perakende Musteri',
        ]);
    }

    public function test_cari_secimsiz_satis_perakende_adi_ayardan_okunur(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('PERAKENDE-AD');
        $stok = $this->stokOlustur($firma, ['stok_miktari' => '10.0000']);
        $this->kasaOlustur($firma);

        app(FirmaAyarDeposu::class)->yaz((int) $firma->id, 'barkodlu_satis_perakende_cari_ad', 'Magaza Musterisi');

        $satis = app(BarkodluSatisServisi::class)->satisTamamla((int) $firma->id, (int) $user->id, [
            'satis_tarihi' => now()->toDateTimeString(),
            'odeme_tipi' => 'nakit',
            'para_birimi' => 'TRY',
            'kalemler' => [[
                'stok_id' => (int) $stok->id,
                'miktar' => 1,
                'birim_fiyat' => 100,
                'kdv_orani' => 20,
            ]],
        ]);

        $this->assertDatabaseHas('cariler', [
            'id' => (int) $satis->cari_id,
            'firma_id' => (int) $firma->id,
            'kod' => 'PERAKENDE-MUSTERI',
            'ad' => 'Magaza Musterisi',
        ]);
    }

    public function test_nakit_satis_secili_kasaya_kaydedilir(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('NAKIT-KASA');
        $stok = $this->stokOlustur($firma, ['stok_miktari' => '10.0000']);
        $this->kasaOlustur($firma);
        $kasa2 = $this->kasaOlustur($firma);

        $satis = app(BarkodluSatisServisi::class)->satisTamamla((int) $firma->id, (int) $user->id, [
            'satis_tarihi' => now()->toDateTimeString(),
            'odeme_tipi' => 'nakit',
            'kasa_hesap_id' => (int) $kasa2->id,
            'para_birimi' => 'TRY',
            'kalemler' => [[
                'stok_id' => (int) $stok->id,
                'miktar' => 1,
                'birim_fiyat' => 100,
                'kdv_orani' => 20,
            ]],
        ]);

        $this->assertDatabaseHas('kasa_hareketleri', [
            'firma_id' => (int) $firma->id,
            'kasa_hesap_id' => (int) $kasa2->id,
        ]);
        $this->assertDatabaseHas('finans_hareketleri', [
            'firma_id' => (int) $firma->id,
            'referans_turu' => 'barkodlu_satis',
            'referans_id' => (int) $satis->id,
        ]);
    }

    public function test_kart_satis_tahsilat_kaydi_olusturur(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('KART');
        $stok = $this->stokOlustur($firma, ['stok_miktari' => '10.0000']);
        $this->posOlustur($firma);

        $satis = app(BarkodluSatisServisi::class)->satisTamamla((int) $firma->id, (int) $user->id, [
            'satis_tarihi' => now()->toDateTimeString(),
            'odeme_tipi' => 'kart',
            'para_birimi' => 'TRY',
            'kalemler' => [[
                'stok_id' => (int) $stok->id,
                'miktar' => 1,
                'birim_fiyat' => 100,
                'kdv_orani' => 20,
            ]],
        ]);

        $finans = FinansHareketi::query()
            ->where('firma_id', (int) $firma->id)
            ->where('referans_turu', 'barkodlu_satis')
            ->where('referans_id', (int) $satis->id)
            ->where('durum', FinansHareketDurumu::Aktif->value)
            ->first();

        $this->assertNotNull($finans);
        $this->assertDatabaseHas('pos_hareketleri', [
            'finans_hareket_id' => (int) $finans->id,
            'firma_id' => (int) $firma->id,
            'para_birimi' => 'TRY',
        ]);
    }

    public function test_banka_ve_pos_hesabi_yoksa_satis_ve_stok_hareketi_geri_alinir(): void
    {
        foreach (['havale', 'kart'] as $odemeTipi) {
            [$user, $firma] = $this->superAdminVeFirmaSession('HESAP-YOK-'.$odemeTipi);
            $stok = $this->stokOlustur($firma, ['stok_miktari' => '10.0000']);

            try {
                app(BarkodluSatisServisi::class)->satisTamamla((int) $firma->id, (int) $user->id, [
                    'satis_tarihi' => now()->toDateTimeString(),
                    'odeme_tipi' => $odemeTipi,
                    'para_birimi' => 'TRY',
                    'kalemler' => [[
                        'stok_id' => (int) $stok->id,
                        'miktar' => 1,
                        'birim_fiyat' => 100,
                        'kdv_orani' => 20,
                    ]],
                ]);
                $this->fail($odemeTipi.' hesabı yokken satış tamamlanmamalı.');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('finans hareketi oluşturulamadı', $e->getMessage());
            }

            $this->assertDatabaseMissing('muhasebe_barkodlu_satislar', [
                'firma_id' => (int) $firma->id,
            ]);
            $this->assertDatabaseMissing('stok_hareketleri', [
                'firma_id' => (int) $firma->id,
                'stok_id' => (int) $stok->id,
            ]);
        }
    }

    public function test_kart_satis_secili_posa_kaydedilir(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('KART-POS');
        $stok = $this->stokOlustur($firma, ['stok_miktari' => '10.0000']);
        $this->posOlustur($firma);
        $pos2 = $this->posOlustur($firma);

        $satis = app(BarkodluSatisServisi::class)->satisTamamla((int) $firma->id, (int) $user->id, [
            'satis_tarihi' => now()->toDateTimeString(),
            'odeme_tipi' => 'kart',
            'pos_hesap_id' => (int) $pos2->id,
            'para_birimi' => 'TRY',
            'kalemler' => [[
                'stok_id' => (int) $stok->id,
                'miktar' => 1,
                'birim_fiyat' => 100,
                'kdv_orani' => 20,
            ]],
        ]);

        $this->assertDatabaseHas('pos_hareketleri', [
            'firma_id' => (int) $firma->id,
            'pos_hesap_id' => (int) $pos2->id,
        ]);
        $this->assertDatabaseHas('finans_hareketleri', [
            'firma_id' => (int) $firma->id,
            'referans_turu' => 'barkodlu_satis',
            'referans_id' => (int) $satis->id,
        ]);
    }

    public function test_havale_satis_tahsilat_kaydi_banka_hareketine_yansir(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('HAVALE');
        $stok = $this->stokOlustur($firma, ['stok_miktari' => '10.0000']);
        $banka = $this->bankaOlustur($firma);

        $satis = app(BarkodluSatisServisi::class)->satisTamamla((int) $firma->id, (int) $user->id, [
            'satis_tarihi' => now()->toDateTimeString(),
            'odeme_tipi' => 'havale',
            'banka_hesap_id' => (int) $banka->id,
            'para_birimi' => 'TRY',
            'kalemler' => [[
                'stok_id' => (int) $stok->id,
                'miktar' => 1,
                'birim_fiyat' => 100,
                'kdv_orani' => 20,
            ]],
        ]);

        $this->assertDatabaseHas('finans_hareketleri', [
            'firma_id' => (int) $firma->id,
            'referans_turu' => 'barkodlu_satis',
            'referans_id' => (int) $satis->id,
            'durum' => FinansHareketDurumu::Aktif->value,
        ]);
        $this->assertDatabaseHas('banka_hareketleri', [
            'firma_id' => (int) $firma->id,
            'banka_hesap_id' => (int) $banka->id,
            'para_birimi' => 'TRY',
        ]);
    }

    public function test_satis_iptalinde_tahsilat_finansi_terslenir(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('IPTAL');
        $stok = $this->stokOlustur($firma, ['stok_miktari' => '10.0000']);
        $cari = $this->cariOlustur($firma);
        $kasa = $this->kasaOlustur($firma);

        $satis = app(BarkodluSatisServisi::class)->satisTamamla((int) $firma->id, (int) $user->id, [
            'satis_tarihi' => now()->toDateTimeString(),
            'cari_id' => (int) $cari->id,
            'odeme_tipi' => 'nakit',
            'para_birimi' => 'TRY',
            'kalemler' => [[
                'stok_id' => (int) $stok->id,
                'miktar' => 1,
                'birim_fiyat' => 100,
                'kdv_orani' => 20,
            ]],
        ]);

        $aktifFinans = FinansHareketi::query()
            ->where('firma_id', (int) $firma->id)
            ->where('referans_turu', 'barkodlu_satis')
            ->where('referans_id', (int) $satis->id)
            ->where('durum', FinansHareketDurumu::Aktif->value)
            ->first();
        $this->assertNotNull($aktifFinans);

        app(BarkodluSatisServisi::class)->satisIptalEt((int) $firma->id, (int) $satis->id, (int) $user->id, 'iptal');

        $this->assertDatabaseHas('finans_hareketleri', [
            'id' => (int) $aktifFinans->id,
            'durum' => FinansHareketDurumu::Iptal->value,
        ]);
        $this->assertDatabaseHas('finans_hareketleri', [
            'iptal_edilen_hareket_id' => (int) $aktifFinans->id,
            'durum' => FinansHareketDurumu::Aktif->value,
        ]);
        $this->assertDatabaseHas('kasa_hareketleri', [
            'kasa_hesap_id' => (int) $kasa->id,
            'durum' => 'iptal',
        ]);
    }

    public function test_iade_edilmis_satis_iptali_hareketleri_degistirmez(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('IADE-IPTAL');
        $stok = $this->stokOlustur($firma, ['stok_miktari' => '10.0000']);
        $cari = $this->cariOlustur($firma);
        $kasa = $this->kasaOlustur($firma);

        $satis = app(BarkodluSatisServisi::class)->satisTamamla((int) $firma->id, (int) $user->id, [
            'satis_tarihi' => now()->toDateTimeString(),
            'cari_id' => (int) $cari->id,
            'odeme_tipi' => 'nakit',
            'kasa_hesap_id' => (int) $kasa->id,
            'para_birimi' => 'TRY',
            'kalemler' => [[
                'stok_id' => (int) $stok->id,
                'miktar' => 1,
                'birim_fiyat' => 100,
                'kdv_orani' => 20,
            ]],
        ]);

        $kalemId = (int) $satis->kalemler()->value('id');
        $iade = app(BarkodluSatisServisi::class)->satisKalemiIadeEt(
            (int) $firma->id,
            (int) $satis->id,
            $kalemId,
            1.0,
            (int) $user->id,
            'iptal kural testi'
        );

        $stokMiktari = (string) $stok->fresh()->stok_miktari;
        $stokHareketSayisi = StokHareketi::query()
            ->where('firma_id', (int) $firma->id)
            ->where('stok_id', (int) $stok->id)
            ->count();

        try {
            app(BarkodluSatisServisi::class)->satisIptalEt(
                (int) $firma->id,
                (int) $satis->id,
                (int) $user->id,
                'iade sonrasi iptal'
            );
            $this->fail('Iade kaydi bulunan satis iptal edilmemelidir.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Iade kaydi bulunan satis iptal edilemez', $e->getMessage());
        }

        $this->assertSame('tamamlandi', (string) $satis->fresh()->durum);
        $this->assertSame($stokMiktari, (string) $stok->fresh()->stok_miktari);
        $this->assertSame($stokHareketSayisi, StokHareketi::query()
            ->where('firma_id', (int) $firma->id)
            ->where('stok_id', (int) $stok->id)
            ->count());
        $this->assertDatabaseHas('muhasebe_barkodlu_satis_iadeler', [
            'id' => (int) $iade->id,
            'satis_id' => (int) $satis->id,
        ]);
        $this->assertDatabaseHas('finans_hareketleri', [
            'firma_id' => (int) $firma->id,
            'referans_turu' => 'barkodlu_satis',
            'referans_id' => (int) $satis->id,
            'durum' => FinansHareketDurumu::Aktif->value,
        ]);
    }

    public function test_iptal_edilen_satis_alacak_ozetinde_acik_tutar_uretmez(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('IPTAL-OZET');
        $stok = $this->stokOlustur($firma, ['stok_miktari' => '10.0000']);
        $cari = $this->cariOlustur($firma);
        $kasa = $this->kasaOlustur($firma);

        $satis = app(BarkodluSatisServisi::class)->satisTamamla((int) $firma->id, (int) $user->id, [
            'satis_tarihi' => now()->toDateTimeString(),
            'cari_id' => (int) $cari->id,
            'odeme_tipi' => 'nakit',
            'kasa_hesap_id' => (int) $kasa->id,
            'para_birimi' => 'TRY',
            'kalemler' => [[
                'stok_id' => (int) $stok->id,
                'miktar' => 1,
                'birim_fiyat' => 100,
                'kdv_orani' => 20,
            ]],
        ]);

        app(BarkodluSatisServisi::class)->satisIptalEt(
            (int) $firma->id,
            (int) $satis->id,
            (int) $user->id,
            'ozet testi'
        );

        $ozet = app(BarkodluSatisAlacakOzetServisi::class)->ozet($satis->fresh());

        $this->assertSame('kapali', $ozet['durum']);
        $this->assertSame('Tam', $ozet['durum_etiketi']);
        $this->assertSame(0.0, (float) $ozet['finansal_acik_tutar']);
        $this->assertSame(0.0, (float) $ozet['plansiz_kalan_tutar']);
    }

    public function test_satis_iadesinde_iade_odeme_finansi_olusturulur(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('IADE-FIN');
        $stok = $this->stokOlustur($firma, ['stok_miktari' => '10.0000']);
        $cari = $this->cariOlustur($firma);
        $kasa = $this->kasaOlustur($firma);

        $satis = app(BarkodluSatisServisi::class)->satisTamamla((int) $firma->id, (int) $user->id, [
            'satis_tarihi' => now()->toDateTimeString(),
            'cari_id' => (int) $cari->id,
            'odeme_tipi' => 'nakit',
            'kasa_hesap_id' => (int) $kasa->id,
            'para_birimi' => 'TRY',
            'kalemler' => [[
                'stok_id' => (int) $stok->id,
                'miktar' => 1,
                'birim_fiyat' => 100,
                'kdv_orani' => 20,
            ]],
        ]);

        $kalemId = (int) $satis->kalemler()->value('id');
        $iade = app(BarkodluSatisServisi::class)->satisKalemiIadeEt(
            (int) $firma->id,
            (int) $satis->id,
            $kalemId,
            1.0,
            (int) $user->id,
            'test iade'
        );

        $this->assertDatabaseHas('finans_hareketleri', [
            'firma_id' => (int) $firma->id,
            'referans_turu' => 'barkodlu_satis_iade',
            'referans_id' => (int) $iade->id,
            'durum' => FinansHareketDurumu::Aktif->value,
        ]);
    }

    public function test_iade_geri_alininca_iade_finansi_terslenir(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('IADE-FIN-TERS');
        $stok = $this->stokOlustur($firma, ['stok_miktari' => '10.0000']);
        $cari = $this->cariOlustur($firma);
        $kasa = $this->kasaOlustur($firma);

        $satis = app(BarkodluSatisServisi::class)->satisTamamla((int) $firma->id, (int) $user->id, [
            'satis_tarihi' => now()->toDateTimeString(),
            'cari_id' => (int) $cari->id,
            'odeme_tipi' => 'nakit',
            'kasa_hesap_id' => (int) $kasa->id,
            'para_birimi' => 'TRY',
            'kalemler' => [[
                'stok_id' => (int) $stok->id,
                'miktar' => 1,
                'birim_fiyat' => 100,
                'kdv_orani' => 20,
            ]],
        ]);

        $kalemId = (int) $satis->kalemler()->value('id');
        $iade = app(BarkodluSatisServisi::class)->satisKalemiIadeEt(
            (int) $firma->id,
            (int) $satis->id,
            $kalemId,
            1.0,
            (int) $user->id,
            'test iade geri al'
        );

        $aktifIadeFinans = FinansHareketi::query()
            ->where('firma_id', (int) $firma->id)
            ->where('referans_turu', 'barkodlu_satis_iade')
            ->where('referans_id', (int) $iade->id)
            ->where('durum', FinansHareketDurumu::Aktif->value)
            ->first();
        $this->assertNotNull($aktifIadeFinans);

        app(BarkodluSatisServisi::class)->iadeKaydiniGeriAl(
            (int) $firma->id,
            (int) $iade->id,
            (int) $user->id,
            'geri al'
        );

        $this->assertDatabaseHas('finans_hareketleri', [
            'id' => (int) $aktifIadeFinans->id,
            'durum' => FinansHareketDurumu::Iptal->value,
        ]);
        $this->assertDatabaseHas('finans_hareketleri', [
            'iptal_edilen_hareket_id' => (int) $aktifIadeFinans->id,
            'durum' => FinansHareketDurumu::Aktif->value,
        ]);
        $this->assertDatabaseMissing('muhasebe_barkodlu_satis_iadeler', [
            'id' => (int) $iade->id,
        ]);
    }

    public function test_satis_fisi_sayfasi_satis_ve_tahsilat_verisini_yukler(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('FIS');
        $stok = $this->stokOlustur($firma, ['stok_miktari' => '10.0000']);
        $this->kasaOlustur($firma);

        $satis = app(BarkodluSatisServisi::class)->satisTamamla((int) $firma->id, (int) $user->id, [
            'satis_tarihi' => now()->toDateTimeString(),
            'odeme_tipi' => 'nakit',
            'para_birimi' => 'TRY',
            'kalemler' => [[
                'stok_id' => (int) $stok->id,
                'miktar' => 1,
                'birim_fiyat' => 100,
                'kdv_orani' => 20,
            ]],
        ]);

        app()->instance('request', Request::create('/test', 'GET', [
            'satis' => (int) $satis->id,
        ]));
        $sayfa = app(BarkodluSatisFisiSayfasi::class);
        $sayfa->mount();

        $this->assertNotNull($sayfa->satis);
        $this->assertNotNull($sayfa->tahsilat);
        $this->assertSame((int) $satis->id, (int) $sayfa->satis->id);
    }

    public function test_satis_fisi_auto_print_parametresini_okur(): void
    {
        [, $firma] = $this->superAdminVeFirmaSession('FIS-AUTO');
        $stok = $this->stokOlustur($firma, ['stok_miktari' => '10.0000']);
        $this->kasaOlustur($firma);

        $satis = app(BarkodluSatisServisi::class)->satisTamamla((int) $firma->id, (int) auth()->id(), [
            'satis_tarihi' => now()->toDateTimeString(),
            'odeme_tipi' => 'nakit',
            'para_birimi' => 'TRY',
            'kalemler' => [[
                'stok_id' => (int) $stok->id,
                'miktar' => 1,
                'birim_fiyat' => 100,
                'kdv_orani' => 20,
            ]],
        ]);

        app()->instance('request', Request::create('/test', 'GET', [
            'satis' => (int) $satis->id,
            'auto_print' => 1,
        ]));

        $sayfa = app(BarkodluSatisFisiSayfasi::class);
        $sayfa->mount();

        $this->assertTrue($sayfa->otomatikYazdir);
    }

    public function test_alternatif_barkod_ile_urun_sepete_eklenir(): void
    {
        [, $firma] = $this->superAdminVeFirmaSession('ALT-BRK');
        $stok = $this->stokOlustur($firma, [
            'barkod' => null,
            'stok_miktari' => '10.0000',
        ]);

        StokBarkodu::query()->create([
            'firma_id' => (int) $firma->id,
            'stok_id' => (int) $stok->id,
            'barkod' => 'ALT-123456',
            'aktif' => true,
            'varsayilan_mi' => false,
        ]);

        $sayfa = app(BarkodluSatisSayfasi::class);
        $sayfa->mount();
        $sayfa->data['barkod'] = 'ALT-123456';
        $sayfa->barkodEkle();

        $this->assertSame((int) $stok->id, (int) ($sayfa->kalemler[0]['stok_id'] ?? 0));
        $this->assertSame(1.0, (float) ($sayfa->kalemler[0]['miktar'] ?? 0));
    }

    public function test_sepet_beklet_ve_geri_yukle_akisi_calisir(): void
    {
        [, $firma] = $this->superAdminVeFirmaSession('BEKLET');
        $stok = $this->stokOlustur($firma, ['stok_miktari' => '10.0000']);

        $sayfa = app(BarkodluSatisSayfasi::class);
        $sayfa->mount();
        $sayfa->data['barkod'] = (string) ($stok->kod ?? '');
        $sayfa->barkodEkle();

        $this->assertCount(1, $sayfa->kalemler);

        $sayfa->sepetBeklet();
        $this->assertCount(0, $sayfa->kalemler);
        $this->assertCount(1, $sayfa->bekleyenSepetler);

        $sayfa->bekleyenSepetiYukle(0);
        $this->assertCount(1, $sayfa->kalemler);
        $this->assertCount(1, $sayfa->bekleyenSepetler);

        $cacheKey = 'barkodlu_satis:bekleyen_sepetler:firma:'.$firma->id.':kullanici:'.auth()->id();
        $cacheData = Cache::get($cacheKey, []);
        $this->assertIsArray($cacheData);
    }

    public function test_hizli_satis_sepet_satirindan_urun_hizli_duzenlenir(): void
    {
        [, $firma] = $this->superAdminVeFirmaSession('HIZLI-DUZENLE');
        $stok = $this->stokOlustur($firma, [
            'ad' => 'Eski Ürün',
            'stok_miktari' => '10.0000',
            'satis_fiyati' => '100.00',
            'indirimli_fiyat' => '0.00',
            'kdv_orani' => '20.00',
        ]);

        $sayfa = app(HizliSatisSayfasi::class);
        $sayfa->mount();
        $sayfa->data['barkod'] = (string) ($stok->kod ?? '');
        $sayfa->barkodEkle();

        $sayfa->hizliKalemDuzenleAc(0);
        $sayfa->hizliKalemDuzenleme['ad'] = 'Yeni Hızlı Ürün';
        $sayfa->hizliKalemDuzenleme['stok_miktari'] = '25.0000';
        $sayfa->hizliKalemDuzenleme['satis_fiyati'] = '150.50';
        $sayfa->hizliKalemDuzenleme['indirimli_fiyat'] = '120.25';
        $sayfa->hizliKalemDuzenleme['kdv_orani'] = '10.00';
        $sayfa->hizliKalemDuzenleKaydet();

        $stok->refresh();
        $this->assertSame('Yeni Hızlı Ürün', $stok->ad);
        $this->assertSame(25.0, (float) $stok->stok_miktari);
        $this->assertSame(150.5, (float) $stok->satis_fiyati);
        $this->assertSame(120.25, (float) $stok->indirimli_fiyat);
        $this->assertSame('Yeni Hızlı Ürün', $sayfa->kalemler[0]['stok_adi'] ?? null);
        $this->assertSame(120.25, (float) ($sayfa->kalemler[0]['birim_fiyat'] ?? 0));
        $this->assertFalse($sayfa->hizliKalemDuzenlemeAcik);
    }

    public function test_hizli_satis_renderless_sepet_kalemini_siler(): void
    {
        [, $firma] = $this->superAdminVeFirmaSession('HIZLI-SIL');
        $stokA = $this->stokOlustur($firma, ['ad' => 'Silinecek Ürün']);
        $stokB = $this->stokOlustur($firma, ['ad' => 'Kalacak Ürün']);

        $sayfa = app(HizliSatisSayfasi::class);
        $sayfa->mount();
        $sayfa->hizliUrunKartindanEkle((int) $stokA->id);
        $sayfa->hizliUrunKartindanEkle((int) $stokB->id);

        $this->assertCount(2, $sayfa->kalemler);

        $sayfa->hizliKalemSil(0);

        $this->assertCount(1, $sayfa->kalemler);
        $this->assertSame('Kalacak Ürün', $sayfa->kalemler[0]['stok_adi'] ?? null);
    }

    public function test_hizli_satis_hizli_urun_ekleme_stok_karti_olusturup_sepete_ekler(): void
    {
        [, $firma] = $this->superAdminVeFirmaSession('HIZLI-URUN');

        $sayfa = app(HizliSatisSayfasi::class);
        $sayfa->mount();
        $sayfa->hizliUrunEkleAc();
        $sayfa->hizliUrunEkleme['barkod'] = '8690000000011';
        $sayfa->hizliUrunEkleme['ad'] = 'Hızlı Eklenen Ürün';
        $sayfa->hizliUrunEkleme['stok_miktari'] = '7.0000';
        $sayfa->hizliUrunEkleme['satis_fiyati'] = '55.25';
        $sayfa->hizliUrunEkleme['kdv_orani'] = '20.00';
        $sayfa->hizliUrunEkleKaydet();

        $this->assertDatabaseHas('stok_kartlari', [
            'firma_id' => (int) $firma->id,
            'barkod' => '8690000000011',
            'ad' => 'Hızlı Eklenen Ürün',
        ]);
        $this->assertDatabaseHas('stok_barkodlari', [
            'firma_id' => (int) $firma->id,
            'barkod' => '8690000000011',
            'aktif' => true,
        ]);
        $this->assertCount(1, $sayfa->kalemler);
        $this->assertSame('Hızlı Eklenen Ürün', $sayfa->kalemler[0]['stok_adi'] ?? null);
        $this->assertFalse($sayfa->hizliUrunEklemeAcik);
    }

    public function test_hizli_satis_urun_kartlarini_toplu_tek_istekte_sepete_ekler(): void
    {
        [, $firma] = $this->superAdminVeFirmaSession('HIZLI-TOPLU');
        $birinci = $this->stokOlustur($firma, ['kod' => 'TOPLU-001', 'ad' => 'Toplu Urun 1']);
        $ikinci = $this->stokOlustur($firma, ['kod' => 'TOPLU-002', 'ad' => 'Toplu Urun 2']);

        $sayfa = app(HizliSatisSayfasi::class);
        $sayfa->mount();
        $sayfa->hizliUrunKartlariniTopluEkle([
            (int) $birinci->id,
            (int) $ikinci->id,
            (int) $birinci->id,
        ]);

        $this->assertCount(2, $sayfa->kalemler);
        $this->assertSame(2.0, (float) ($sayfa->kalemler[0]['miktar'] ?? 0));
        $this->assertSame(1.0, (float) ($sayfa->kalemler[1]['miktar'] ?? 0));
        $this->assertSame((int) $ikinci->id, (int) ($sayfa->kalemler[1]['stok_id'] ?? 0));
    }

    public function test_hizli_satis_hizli_urun_ekleme_kdv_dahil_fiyati_nete_cevirir(): void
    {
        [, $firma] = $this->superAdminVeFirmaSession('KDV-DAHIL');

        $sayfa = app(HizliSatisSayfasi::class);
        $sayfa->mount();
        $sayfa->hizliUrunHizliKaydet(
            '8690000000012',
            'KDV Dahil Ürün',
            '3.0000',
            '120.00',
            '20.00',
            '',
            true
        );

        $stok = StokKarti::query()
            ->where('firma_id', (int) $firma->id)
            ->where('barkod', '8690000000012')
            ->firstOrFail();

        $this->assertSame(100.0, (float) $stok->satis_fiyati);
        $this->assertSame(20.0, (float) $stok->kdv_orani);
        $this->assertSame(100.0, (float) ($sayfa->kalemler[0]['birim_fiyat'] ?? 0));
    }

    public function test_hizli_satis_hizli_urun_ekleme_birim_ve_alis_fiyatini_kaydeder(): void
    {
        [, $firma] = $this->superAdminVeFirmaSession('BIRIM-MALIYET');

        $sayfa = app(HizliSatisSayfasi::class);
        $sayfa->mount();
        $sayfa->hizliUrunHizliKaydet(
            '8690000000099',
            'Paketli Hızlı Ürün',
            '4.0000',
            '150.00',
            '20.00',
            '',
            false,
            '',
            'PKT',
            '90.50'
        );

        $stok = StokKarti::query()
            ->where('firma_id', (int) $firma->id)
            ->where('barkod', '8690000000099')
            ->firstOrFail();

        $this->assertSame('PKT', (string) $stok->birim);
        $this->assertSame(90.5, (float) $stok->alis_fiyati);
        $this->assertSame('PKT', (string) ($sayfa->kalemler[0]['birim'] ?? ''));
    }

    public function test_hizli_satis_hizli_urun_ekleme_baslangic_stok_hareketi_olusturur(): void
    {
        [, $firma] = $this->superAdminVeFirmaSession('HIZLI-STOK-HAREKET');

        $sayfa = app(HizliSatisSayfasi::class);
        $sayfa->mount();
        $sayfa->hizliUrunHizliKaydet(
            '8690000000102',
            'Başlangıç Stoklu Ürün',
            '5.0000',
            '180.00',
            '20.00',
            '',
            false,
            '',
            'AD',
            '70.00'
        );

        $stok = StokKarti::query()
            ->where('firma_id', (int) $firma->id)
            ->where('barkod', '8690000000102')
            ->firstOrFail();

        $hareket = StokHareketi::query()
            ->where('firma_id', (int) $firma->id)
            ->where('stok_id', (int) $stok->id)
            ->firstOrFail();

        $this->assertSame(5.0, (float) $stok->stok_miktari);
        $this->assertSame(StokHareketIslemTuru::Alis, $hareket->islem_turu);
        $this->assertSame(StokBelgeTuru::Duzeltme, $hareket->belge_turu);
        $this->assertSame(5.0, (float) $hareket->miktar);
        $this->assertSame(70.0, (float) $hareket->birim_fiyat);
        $this->assertSame(70.0, (float) $hareket->birim_maliyet);
    }

    public function test_hizli_satis_hizli_urun_ekleme_markayi_stok_kartina_kaydeder(): void
    {
        [, $firma] = $this->superAdminVeFirmaSession('MARKA-HIZLI');

        $sayfa = app(HizliSatisSayfasi::class);
        $sayfa->mount();
        $sayfa->hizliUrunHizliKaydet(
            '8690000000100',
            'Markalı Hızlı Ürün',
            '2.0000',
            '250.00',
            '20.00',
            '',
            false,
            '',
            'AD',
            '150.00',
            'Test Marka'
        );

        $stok = StokKarti::query()
            ->where('firma_id', (int) $firma->id)
            ->where('barkod', '8690000000100')
            ->firstOrFail();

        $this->assertSame('Test Marka', (string) $stok->marka_uretici);
    }

    public function test_hizli_satis_hizli_urun_ekleme_kategoriyi_stok_kartina_kaydeder(): void
    {
        [, $firma] = $this->superAdminVeFirmaSession('KATEGORI-HIZLI');
        $kategori = StokKategorisi::query()->create([
            'firma_id' => (int) $firma->id,
            'kod' => 'KAMERA',
            'ad' => 'Kamera',
            'aktif_mi' => true,
            'is_sabit' => false,
        ]);

        $sayfa = app(HizliSatisSayfasi::class);
        $sayfa->mount();
        $sayfa->hizliUrunHizliKaydet(
            '8690000000101',
            'Kategorili Hızlı Ürün',
            '2.0000',
            '250.00',
            '20.00',
            '',
            false,
            '',
            'AD',
            '150.00',
            'Test Marka',
            (int) $kategori->id
        );

        $stok = StokKarti::query()
            ->where('firma_id', (int) $firma->id)
            ->where('barkod', '8690000000101')
            ->firstOrFail();

        $this->assertSame((int) $kategori->id, (int) $stok->kategori_id);
    }

    public function test_hizli_satis_hizli_urun_ekleme_yuklenen_gorseli_stok_kartina_baglar(): void
    {
        Storage::fake('public');
        [, $firma] = $this->superAdminVeFirmaSession('URUN-GORSEL');

        $sayfa = app(HizliSatisSayfasi::class);
        $sayfa->mount();
        $sayfa->hizliUrunHizliKaydet(
            '8690000000013',
            'Görselli Hızlı Ürün',
            '2.0000',
            '75.00',
            '20.00',
            '',
            false,
            'data:image/png;base64,'.$this->birPikselPngBase64()
        );

        $stok = StokKarti::query()
            ->where('firma_id', (int) $firma->id)
            ->where('barkod', '8690000000013')
            ->firstOrFail();

        $this->assertDatabaseHas('stok_karti_gorselleri', [
            'stok_karti_id' => (int) $stok->id,
            'kapak_mi' => true,
            'aktif_mi' => true,
        ]);

        $gorsel = $stok->gorseller()->firstOrFail();
        Storage::disk('public')->assertExists((string) $gorsel->dosya_yolu);
        $this->assertStringStartsWith('stok/gallery/hizli-urun-', (string) $gorsel->dosya_yolu);
    }

    public function test_hizli_satis_hizli_urun_ekleme_gecersiz_gorseli_stok_kartina_baglamaz(): void
    {
        Storage::fake('public');
        [, $firma] = $this->superAdminVeFirmaSession('URUN-GORSEL-HATALI');

        $sayfa = app(HizliSatisSayfasi::class);
        $sayfa->mount();
        $sayfa->hizliUrunHizliKaydet(
            '8690000000014',
            'Hatalı Görsel Ürün',
            '2.0000',
            '75.00',
            '20.00',
            '',
            false,
            'data:image/png;base64,'.base64_encode('bu bir gorsel degil')
        );

        $stok = StokKarti::query()
            ->where('firma_id', (int) $firma->id)
            ->where('barkod', '8690000000014')
            ->firstOrFail();

        $this->assertDatabaseMissing('stok_karti_gorselleri', [
            'stok_karti_id' => (int) $stok->id,
        ]);
    }

    public function test_hizli_satis_hizli_urun_ekleme_url_gorselini_dogrulayip_baglar(): void
    {
        Storage::fake('public');
        Http::fake([
            'https://example.test/urun.png' => Http::response(base64_decode($this->birPikselPngBase64()), 200, [
                'content-type' => 'image/png',
            ]),
        ]);
        [, $firma] = $this->superAdminVeFirmaSession('URUN-GORSEL-URL');

        $sayfa = app(HizliSatisSayfasi::class);
        $sayfa->mount();
        $sayfa->hizliUrunHizliKaydet(
            '8690000000015',
            'URL Görselli Hızlı Ürün',
            '2.0000',
            '75.00',
            '20.00',
            'https://example.test/urun.png'
        );

        $stok = StokKarti::query()
            ->where('firma_id', (int) $firma->id)
            ->where('barkod', '8690000000015')
            ->firstOrFail();

        $gorsel = $stok->gorseller()->firstOrFail();
        Storage::disk('public')->assertExists((string) $gorsel->dosya_yolu);
        $this->assertStringEndsWith('.png', (string) $gorsel->dosya_yolu);
    }

    public function test_hizli_satis_hizli_urun_ekleme_kendi_gorseli_url_gorselinden_once_kullanir(): void
    {
        Storage::fake('public');
        Http::fake([
            'https://example.test/urun.png' => Http::response(base64_decode($this->birPikselPngBase64()), 200, [
                'content-type' => 'image/png',
            ]),
        ]);
        [, $firma] = $this->superAdminVeFirmaSession('URUN-GORSEL-ONCELIK');

        $sayfa = app(HizliSatisSayfasi::class);
        $sayfa->mount();
        $sayfa->hizliUrunHizliKaydet(
            '8690000000018',
            'Kendi Görselli Hızlı Ürün',
            '2.0000',
            '75.00',
            '20.00',
            'https://example.test/urun.png',
            false,
            'data:image/png;base64,'.$this->birPikselPngBase64()
        );

        $stok = StokKarti::query()
            ->where('firma_id', (int) $firma->id)
            ->where('barkod', '8690000000018')
            ->firstOrFail();

        $gorsel = $stok->gorseller()->firstOrFail();
        $this->assertStringStartsWith('stok/gallery/hizli-urun-', (string) $gorsel->dosya_yolu);
        $this->assertStringEndsWith('.png', (string) $gorsel->dosya_yolu);
        Http::assertNothingSent();
    }

    public function test_hizli_satis_hizli_urun_ekleme_gorselini_temizler(): void
    {
        $this->superAdminVeFirmaSession('URUN-GORSEL-TEMIZLE');

        $sayfa = app(HizliSatisSayfasi::class);
        $sayfa->mount();
        $sayfa->hizliUrunEkleAc('8690000000017');
        $sayfa->hizliUrunEkleme['gorsel_url'] = 'https://example.test/urun.png';
        $sayfa->hizliUrunEkleme['gorsel_data_url'] = 'data:image/png;base64,'.$this->birPikselPngBase64();
        $sayfa->hizliUrunGorseliniTemizle();

        $this->assertSame('', $sayfa->hizliUrunEkleme['gorsel_url'] ?? null);
        $this->assertSame('', $sayfa->hizliUrunEkleme['gorsel_data_url'] ?? null);
        $this->assertNull($sayfa->hizliUrunGorselDosyasi);
    }

    public function test_hizli_satis_hizli_urun_ekleme_gecersiz_url_gorselini_baglamaz(): void
    {
        Storage::fake('public');
        Http::fake([
            'https://example.test/urun.png' => Http::response('gorsel degil', 200, [
                'content-type' => 'image/png',
            ]),
        ]);
        [, $firma] = $this->superAdminVeFirmaSession('URUN-GORSEL-URL-HATALI');

        $sayfa = app(HizliSatisSayfasi::class);
        $sayfa->mount();
        $sayfa->hizliUrunHizliKaydet(
            '8690000000016',
            'Hatalı URL Görsel Ürün',
            '2.0000',
            '75.00',
            '20.00',
            'https://example.test/urun.png'
        );

        $stok = StokKarti::query()
            ->where('firma_id', (int) $firma->id)
            ->where('barkod', '8690000000016')
            ->firstOrFail();

        $this->assertDatabaseMissing('stok_karti_gorselleri', [
            'stok_karti_id' => (int) $stok->id,
        ]);
    }

    public function test_hizli_satis_vergi_oranlari_sistem_tanimlarindan_gelir_ve_yirmi_varsayilan_kalir(): void
    {
        [, $firma] = $this->superAdminVeFirmaSession('KDV-SECENEK');

        VergiOrani::query()->create([
            'firma_id' => (int) $firma->id,
            'kod' => 'KDV10',
            'ad' => 'KDV 10',
            'oran' => '10.0000',
            'aktif_mi' => true,
            'is_sabit' => false,
        ]);

        $sayfa = app(HizliSatisSayfasi::class);
        $sayfa->mount();

        $oranlar = collect($sayfa->hizliVergiOraniSecenekleri())->pluck('oran')->map(fn ($oran): float => (float) $oran)->all();

        $this->assertContains(10.0, $oranlar);
        $this->assertContains(20.0, $oranlar);
    }

    public function test_hizli_satis_barkoddan_internet_bilgisi_doldurur(): void
    {
        $this->superAdminVeFirmaSession('BARKOD-ARA');

        Http::fake([
            'world.openfoodfacts.org/*' => Http::response([
                'status' => 1,
                'product' => [
                    'brands' => 'Test Marka',
                    'product_name' => 'Test Ürün',
                    'image_front_url' => 'https://example.test/urun.jpg',
                ],
            ], 200),
        ]);

        $sayfa = app(HizliSatisSayfasi::class);
        $sayfa->mount();
        $sayfa->hizliUrunEkleAc('1234567890123');
        $sayfa->hizliUrunBarkoddanAra();

        $this->assertSame('Test Marka Test Ürün', $sayfa->hizliUrunEkleme['ad'] ?? null);
        $this->assertSame('https://example.test/urun.jpg', $sayfa->hizliUrunEkleme['gorsel_url'] ?? null);
        $this->assertSame('Open Food Facts', $sayfa->hizliUrunEkleme['kaynak'] ?? null);
    }

    public function test_hizli_satis_barkoddan_open_products_fallback_bilgisi_doldurur(): void
    {
        $this->superAdminVeFirmaSession('BARKOD-OPF');

        Http::fake([
            'world.openfoodfacts.org/*' => Http::response(['status' => 0], 200),
            'world.openproductsfacts.org/*' => Http::response([
                'status' => 1,
                'product' => [
                    'brands' => 'Elektronik Marka',
                    'product_name' => 'Type-C Şarj Seti',
                    'image_front_url' => 'https://example.test/sarj.jpg',
                ],
            ], 200),
        ]);

        $sayfa = app(HizliSatisSayfasi::class);
        $sayfa->mount();
        $sayfa->hizliUrunEkleAc('9990001112223');
        $sayfa->hizliUrunBarkoddanAra();

        $this->assertSame('Elektronik Marka Type-C Şarj Seti', $sayfa->hizliUrunEkleme['ad'] ?? null);
        $this->assertSame('https://example.test/sarj.jpg', $sayfa->hizliUrunEkleme['gorsel_url'] ?? null);
        $this->assertSame('Open Products Facts', $sayfa->hizliUrunEkleme['kaynak'] ?? null);
    }

    public function test_hizli_satis_nodar_barkodunu_uretici_katalogundan_doldurur(): void
    {
        $this->superAdminVeFirmaSession('NODAR-BARKOD');

        Http::fake([
            '*' => Http::response([], 404),
        ]);

        $sayfa = app(HizliSatisSayfasi::class);
        $sayfa->mount();
        $sayfa->hizliUrunEkleAc('8684886010074');
        $sayfa->hizliUrunBarkoddanAra();

        $this->assertSame('Nodar ND1007 Type-C To Type-C Super Fast Şarj Seti 45W Siyah', $sayfa->hizliUrunEkleme['ad'] ?? null);
        $this->assertSame('https://i0fz9hj7wjpz.merlincdn.net/Resim/Minik/1500x1500_thumb_nd1007.jpg?v=2', $sayfa->hizliUrunEkleme['gorsel_url'] ?? null);
        $this->assertSame('Nodar üretici kataloğu', $sayfa->hizliUrunEkleme['kaynak'] ?? null);
    }

    public function test_hizli_satis_dogrulanmis_barkod_katalogunu_arar(): void
    {
        $this->superAdminVeFirmaSession('HADRON-BARKOD');

        Http::fake([
            '*' => Http::response([], 404),
        ]);

        $sayfa = app(HizliSatisSayfasi::class);
        $sayfa->mount();
        $sayfa->hizliUrunEkleAc('8680469000555');
        $sayfa->hizliUrunBarkoddanAra();

        $this->assertSame('8680469000555', $sayfa->hizliUrunEkleme['barkod'] ?? null);
        $this->assertSame('Hadron', $sayfa->hizliUrunEkleme['marka_uretici'] ?? null);
        $this->assertStringContainsString('Hadron HD702/50', $sayfa->hizliUrunEkleme['ad'] ?? '');
        $this->assertSame('https://cdn.dsmcdn.com/mnresize/420/620/ty1614/prod/QC/20241221/04/e7f6c475-03d2-3340-964d-cc3f0228f97f/1_org.jpg', $sayfa->hizliUrunEkleme['gorsel_url'] ?? null);
        $this->assertSame('Doğrulanmış barkod kataloğu', $sayfa->hizliUrunEkleme['kaynak'] ?? null);
    }

    public function test_hizli_satis_barkoddan_tedarikci_katalogunu_arar(): void
    {
        $this->superAdminVeFirmaSession('TEDARIKCI-BARKOD');

        Http::fake([
            'world.openfoodfacts.org/*' => Http::response([], 404),
            'world.openproductsfacts.org/*' => Http::response([], 404),
            'nodar.com.tr/*' => Http::response('
                <div class="productItemBlock">
                    <div class="productItemImage">
                        <img itemprop="image" src="/Resim/Minik/500x500_thumb_jbr818.jpg?v=2" alt="JBR JBR818 Karaoke Mikrofonlu Bluetooth Hoparlör FM Radyo 40W 8 İnç RGB Kumandalı Siyah">
                    </div>
                    <div class="productItemTitle">
                        <strong itemprop="name">JBR JBR818 Karaoke Mikrofonlu Bluetooth Hoparlör FM Radyo 40W 8 İnç RGB Kumandalı Siyah</strong>
                        <small class="productItemBrand text-center">JBR</small>
                        <small class="productItemBarcode text-center">Barkodu : 8680469052059</small>
                    </div>
                </div>
            ', 200),
            'tesoro.com.tr/*' => Http::response('', 200),
        ]);

        $sayfa = app(HizliSatisSayfasi::class);
        $sayfa->mount();
        $sayfa->hizliUrunEkleAc('8680469052059');
        $sayfa->hizliUrunBarkoddanAra();

        $this->assertSame('JBR JBR818 Karaoke Mikrofonlu Bluetooth Hoparlör FM Radyo 40W 8 İnç RGB Kumandalı Siyah', $sayfa->hizliUrunEkleme['ad'] ?? null);
        $this->assertSame('JBR', $sayfa->hizliUrunEkleme['marka_uretici'] ?? null);
        $this->assertSame('https://nodar.com.tr/Resim/Minik/500x500_thumb_jbr818.jpg?v=2', $sayfa->hizliUrunEkleme['gorsel_url'] ?? null);
        $this->assertSame('NODAR tedarikçi kataloğu', $sayfa->hizliUrunEkleme['kaynak'] ?? null);
    }

    public function test_hizli_satis_hizli_urun_barkod_ararken_okutucu_karakterlerini_temizler(): void
    {
        $this->superAdminVeFirmaSession('BARKOD-TEMIZ');

        Http::fake([
            '*' => Http::response([], 404),
        ]);

        $sayfa = app(HizliSatisSayfasi::class);
        $sayfa->mount();
        $sayfa->hizliUrunEkleAc('868 488-6010074');
        $sayfa->hizliUrunBarkoddanAra();

        $this->assertSame('8684886010074', $sayfa->hizliUrunEkleme['barkod'] ?? null);
        $this->assertSame('Nodar ND1007 Type-C To Type-C Super Fast Şarj Seti 45W Siyah', $sayfa->hizliUrunEkleme['ad'] ?? null);
    }

    public function test_kaydet_ve_yazdir_akisi_satis_kaydi_olusturur(): void
    {
        [, $firma] = $this->superAdminVeFirmaSession('YAZDIR');
        $stok = $this->stokOlustur($firma, ['stok_miktari' => '10.0000']);
        $this->kasaOlustur($firma);

        $sayfa = app(BarkodluSatisSayfasi::class);
        $sayfa->mount();
        $sayfa->data['barkod'] = (string) ($stok->kod ?? '');
        $sayfa->barkodEkle();
        $sayfa->satisiTamamlaVeYazdir();

        $this->assertDatabaseHas('muhasebe_barkodlu_satislar', [
            'firma_id' => (int) $firma->id,
        ]);
        $this->assertCount(0, $sayfa->kalemler);
    }

    public function test_etiket_sayfasi_url_parametresi_ile_onizleme_olusturur(): void
    {
        [, $firma] = $this->superAdminVeFirmaSession('ETIKET-URL');
        $stok = $this->stokOlustur($firma, ['stok_miktari' => '10.0000']);

        app()->instance('request', Request::create('/test', 'GET', [
            'stok_id' => (int) $stok->id,
            'adet' => 2,
        ]));

        $sayfa = app(BarkodEtiketYazdirmaSayfasi::class);
        $sayfa->mount();

        $this->assertCount(2, $sayfa->etiketler);
    }

    public function test_pos_secili_urun_etiket_url_adet_parametresi_tasir(): void
    {
        [, $firma] = $this->superAdminVeFirmaSession('ETK-POS-ADET');
        $stok = $this->stokOlustur($firma, ['stok_miktari' => '10.0000']);

        $sayfa = app(BarkodluSatisSayfasi::class);
        $sayfa->mount();
        $sayfa->data['barkod'] = (string) ($stok->kod ?? '');
        $sayfa->barkodEkle();

        $sayfa->etiketYazdirmaAdediDegistir(5);
        $sayfa->etiketYazdirmaAdediDegistir(10);

        $url = (string) $sayfa->seciliEtiketYazdirUrl();
        $this->assertNotSame('', $url);

        $query = parse_url($url, PHP_URL_QUERY);
        $this->assertIsString($query);
        parse_str((string) $query, $params);

        $this->assertSame('16', (string) ($params['adet'] ?? ''));
        $this->assertSame((string) $stok->id, (string) ($params['stok_id'] ?? ''));
    }

    public function test_barkod_aday_listesinden_tiklayarak_sepete_eklenir(): void
    {
        [, $firma] = $this->superAdminVeFirmaSession('BRK-ADAY');
        $stok = $this->stokOlustur($firma, ['kod' => 'ADAY-001', 'ad' => 'Aday Urun']);

        $sayfa = app(BarkodluSatisSayfasi::class);
        $sayfa->mount();
        $sayfa->updatedDataBarkod('ADAY');

        $this->assertNotEmpty($sayfa->barkodAdaylari);
        $sayfa->barkodAdaydanEkle((int) $stok->id);

        $this->assertCount(1, $sayfa->kalemler);
        $this->assertSame((int) $stok->id, (int) ($sayfa->kalemler[0]['stok_id'] ?? 0));
    }

    public function test_barkodlu_satis_gorunen_stok_turleri_ayarini_kaydeder(): void
    {
        [, $firma] = $this->superAdminVeFirmaSession('STOK-TURU-AYAR');

        $sayfa = app(BarkodluSatisAyarlarSayfasi::class);
        $sayfa->mount();
        $sayfa->data['barkodlu_satis_gorunen_stok_turleri'] = [StokKartiTuru::Hizmet->value];
        $sayfa->kaydet();

        $this->assertSame(
            [StokKartiTuru::Hizmet->value],
            app(FirmaAyarDeposu::class)->oku((int) $firma->id, 'barkodlu_satis_gorunen_stok_turleri', [])
        );
    }

    public function test_barkodlu_satis_stok_turu_ayarina_gore_barkod_ekler(): void
    {
        [, $firma] = $this->superAdminVeFirmaSession('STOK-TURU-BARKOD');
        app(FirmaAyarDeposu::class)->yaz((int) $firma->id, 'barkodlu_satis_gorunen_stok_turleri', [StokKartiTuru::Hizmet->value]);

        $ticariMal = $this->stokOlustur($firma, [
            'barkod' => 'TICARI-001',
            'tur' => StokKartiTuru::TicariMal->value,
        ]);
        $hizmet = $this->stokOlustur($firma, [
            'barkod' => 'HIZMET-001',
            'tur' => StokKartiTuru::Hizmet->value,
        ]);

        $sayfa = app(BarkodluSatisSayfasi::class);
        $sayfa->mount();

        $sayfa->data['barkod'] = (string) $ticariMal->barkod;
        $sayfa->barkodEkle();
        $this->assertCount(0, $sayfa->kalemler);

        $sayfa->data['barkod'] = (string) $hizmet->barkod;
        $sayfa->barkodEkle();
        $this->assertCount(1, $sayfa->kalemler);
        $this->assertSame((int) $hizmet->id, (int) ($sayfa->kalemler[0]['stok_id'] ?? 0));
    }

    public function test_hizli_satis_urun_kartlari_stok_turu_ayarina_gore_gorunur(): void
    {
        [, $firma] = $this->superAdminVeFirmaSession('STOK-TURU-HIZLI');
        app(FirmaAyarDeposu::class)->yaz((int) $firma->id, 'barkodlu_satis_gorunen_stok_turleri', [StokKartiTuru::Hizmet->value]);

        $ticariMal = $this->stokOlustur($firma, [
            'ad' => 'Gizli ticari mal',
            'tur' => StokKartiTuru::TicariMal->value,
        ]);
        $hizmet = $this->stokOlustur($firma, [
            'ad' => 'Gorunen hizmet',
            'tur' => StokKartiTuru::Hizmet->value,
        ]);

        $sayfa = app(HizliSatisSayfasi::class);
        $sayfa->mount();

        $urunIds = collect($sayfa->hizliSatisUrunleri())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertContains((int) $hizmet->id, $urunIds);
        $this->assertNotContains((int) $ticariMal->id, $urunIds);
    }

    private function superAdminVeFirmaSession(string $kod): array
    {
        Cache::flush();

        $firma = Firma::query()->create([
            'ad' => 'Barkod '.$kod,
            'kisa_ad' => $kod,
            'firma_kodu' => 'BRK-'.$kod.'-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);

        $user = User::query()->create([
            'name' => 'SA-'.$kod,
            'email' => 'sa-'.$kod.'-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => true,
        ]);

        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        return [$user, $firma];
    }

    private function stokOlustur(Firma $firma, array $override = []): StokKarti
    {
        return StokKarti::query()->create(array_merge([
            'firma_id' => (int) $firma->id,
            'kod' => 'STK-'.uniqid(),
            'ad' => 'Stok '.uniqid(),
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'stok_takip' => true,
            'stok_miktari' => '10.0000',
            'rezerve_miktar' => '0.0000',
            'para_birimi' => 'TRY',
            'birim' => 'AD',
            'satis_fiyati' => '100.00',
            'alis_fiyati' => '70.00',
        ], $override));
    }

    private function kasaOlustur(Firma $firma): KasaHesabi
    {
        return KasaHesabi::query()->create([
            'firma_id' => (int) $firma->id,
            'kod' => 'KASA-'.uniqid(),
            'ad' => 'Merkez Kasa',
            'para_birimi' => 'TRY',
            'durum' => HesapDurumu::Aktif->value,
        ]);
    }

    private function posOlustur(Firma $firma): PosHesabi
    {
        return PosHesabi::query()->create([
            'firma_id' => (int) $firma->id,
            'kod' => 'POS-'.uniqid(),
            'ad' => 'Ana POS',
            'para_birimi' => 'TRY',
            'durum' => HesapDurumu::Aktif->value,
        ]);
    }

    private function bankaOlustur(Firma $firma): BankaHesabi
    {
        return BankaHesabi::query()->create([
            'firma_id' => (int) $firma->id,
            'kod' => 'BNK-'.uniqid(),
            'ad' => 'Ana Banka',
            'durum' => HesapDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);
    }

    private function cariOlustur(Firma $firma): Cari
    {
        return Cari::query()->create([
            'firma_id' => (int) $firma->id,
            'kod' => 'CAR-'.uniqid(),
            'ad' => 'Musteri '.uniqid(),
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);
    }

    private function birPikselPngBase64(): string
    {
        return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/l5MCVQAAAABJRU5ErkJggg==';
    }
}
