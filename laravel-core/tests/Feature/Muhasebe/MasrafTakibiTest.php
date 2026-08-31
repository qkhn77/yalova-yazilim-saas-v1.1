<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\Masraf\Arac;
use App\Models\Masraf\MasrafButcesi;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\Masraf;
use App\Models\Muhasebe\MasrafKategorisi;
use App\Models\Proje\IsletmeProjesi;
use App\Filament\Clusters\MasrafTakip\Pages\MasrafRaporlariSayfasi;
use App\Models\User;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Servisler\MasrafFaturaBaglantiServisi;
use App\Muhasebe\Servisler\MasrafFaturaKayitServisi;
use App\Muhasebe\Servisler\DuzenliFaturaTanimiServisi;
use App\Muhasebe\Servisler\MasrafKayitServisi;
use App\Muhasebe\Servisler\MasrafKategoriServisi;
use App\Muhasebe\Servisler\MasrafButceServisi;
use App\Muhasebe\Servisler\FaturaIslemServisi;
use App\Services\MuhasebeDisaAktarimServisi;
use App\Services\TenantContextService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MasrafTakibiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hizli_masraf_kaydi_tenant_ve_idempotency_kurallarini_korur(): void
    {
        $firma = $this->firmaOlustur('MASRAF-A');
        $digerFirma = $this->firmaOlustur('MASRAF-B');
        $this->superAdminVeSession($firma);

        MasrafKategorisi::varsayilanlariHazirla($firma->id);
        MasrafKategorisi::varsayilanlariHazirla($digerFirma->id);
        $kategori = MasrafKategorisi::query()->where('firma_id', $firma->id)->where('kod', 'elektrik')->firstOrFail();
        $digerKategori = MasrafKategorisi::query()->where('firma_id', $digerFirma->id)->where('kod', 'elektrik')->firstOrFail();

        $alanlar = [
            'masraf_kategorisi_id' => $kategori->id,
            'tarih' => Carbon::today()->toDateString(),
            'tutar' => '1250,50',
            'para_birimi' => 'TRY',
            'aciklama' => 'Elektrik faturası',
        ];

        $ilk = app(MasrafKayitServisi::class)->kaydet($firma->id, $alanlar, auth()->id(), 'test:masraf:1');
        $ikinci = app(MasrafKayitServisi::class)->kaydet($firma->id, $alanlar, auth()->id(), 'test:masraf:1');

        $this->assertSame($ilk->id, $ikinci->id);
        $this->assertSame('1250.50', (string) $ilk->tutar);
        $this->assertSame(1, Masraf::query()->where('firma_id', $firma->id)->count());
        $this->assertSame(0, Masraf::query()->where('firma_id', $digerFirma->id)->count());

        $this->expectException(IsKuraliIstisnasi::class);
        app(MasrafKayitServisi::class)->kaydet($firma->id, array_merge($alanlar, [
            'masraf_kategorisi_id' => $digerKategori->id,
        ]), auth()->id(), 'test:masraf:2');
    }

    public function test_masraf_belgesi_tenant_kaydi_ile_saklanir_ve_guncellenebilir(): void
    {
        Storage::fake('public');
        $firma = $this->firmaOlustur('MASRAF-BELGE');
        $this->superAdminVeSession($firma);
        MasrafKategorisi::varsayilanlariHazirla($firma->id);
        $kategori = MasrafKategorisi::query()->where('firma_id', $firma->id)->where('kod', 'elektrik')->firstOrFail();
        $yol = 'masraflar/'.$firma->id.'/fatura.pdf';
        Storage::disk('public')->put($yol, '%PDF-1.4 test');

        $masraf = app(MasrafKayitServisi::class)->kaydet($firma->id, [
            'masraf_kategorisi_id' => $kategori->id,
            'tarih' => '2026-08-05', 'tutar' => '250.00', 'para_birimi' => 'TRY',
            'aciklama' => 'Belgelı elektrik gideri', 'belge_yolu' => $yol, 'belge_adi' => 'fatura.pdf',
        ], auth()->id(), 'test:masraf:belge:1');

        $this->assertSame($yol, $masraf->belge_yolu);
        $this->assertSame('application/pdf', $masraf->belge_mime);
        $this->assertSame('fatura.pdf', $masraf->belge_adi);
        Storage::disk('public')->assertExists($yol);

        $guncel = app(MasrafKayitServisi::class)->guncelle($firma->id, $masraf->id, [
            'masraf_kategorisi_id' => $kategori->id, 'tarih' => '2026-08-05', 'aciklama' => 'Belge açıklaması',
            'notlar' => null, 'belge_yolu' => $yol, 'belge_adi' => 'fatura-yeni.pdf',
        ]);
        $this->assertSame('fatura-yeni.pdf', $guncel->belge_adi);
    }

    public function test_iptal_edilen_masraf_silinmez_ve_gelir_gider_raporuna_girmez(): void
    {
        $firma = $this->firmaOlustur('MASRAF-C');
        $this->superAdminVeSession($firma);
        MasrafKategorisi::varsayilanlariHazirla($firma->id);
        $kategori = MasrafKategorisi::query()->where('firma_id', $firma->id)->where('kod', 'arac')->firstOrFail();

        $masraf = app(MasrafKayitServisi::class)->kaydet($firma->id, [
            'masraf_kategorisi_id' => $kategori->id,
            'tarih' => Carbon::today()->toDateString(),
            'tutar' => '50.00',
            'para_birimi' => 'TRY',
            'aciklama' => 'Araç yakıtı',
        ], auth()->id(), 'test:masraf:3');

        $rapor = app(MuhasebeDisaAktarimServisi::class)->gelirGiderOzeti(
            $firma->id,
            Carbon::today()->subDay(),
            Carbon::today()->addDay(),
        );

        $this->assertSame('50.00', $rapor[0]['Gider Toplam']);
        $this->assertSame(1, $rapor[0]['Masraf Adedi']);

        app(MasrafKayitServisi::class)->iptalEt($firma->id, $masraf->id, auth()->id(), 'Yanlış kayıt');

        $this->assertDatabaseHas('masraflar', ['id' => $masraf->id, 'durum' => Masraf::DURUM_IPTAL]);
        $this->assertSame([], app(MuhasebeDisaAktarimServisi::class)->gelirGiderOzeti(
            $firma->id,
            Carbon::today()->subDay(),
            Carbon::today()->addDay(),
        ));
    }

    public function test_aktif_masraf_tenant_ve_fatura_baglantisini_bozmadan_guncellenir(): void
    {
        $firma = $this->firmaOlustur('MASRAF-EDIT');
        $digerFirma = $this->firmaOlustur('MASRAF-EDIT-2');
        $this->superAdminVeSession($firma);
        MasrafKategorisi::varsayilanlariHazirla($firma->id);
        MasrafKategorisi::varsayilanlariHazirla($digerFirma->id);

        $kategori = MasrafKategorisi::query()->where('firma_id', $firma->id)->where('kod', 'elektrik')->firstOrFail();
        $digerKategori = MasrafKategorisi::query()->where('firma_id', $digerFirma->id)->where('kod', 'elektrik')->firstOrFail();
        $masraf = app(MasrafKayitServisi::class)->kaydet($firma->id, [
            'masraf_kategorisi_id' => $kategori->id,
            'tarih' => '2026-08-01', 'tutar' => '125.00', 'para_birimi' => 'TRY', 'aciklama' => 'Eski açıklama',
        ], auth()->id(), 'test:masraf:edit:1');

        $guncel = app(MasrafKayitServisi::class)->guncelle($firma->id, $masraf->id, [
            'masraf_kategorisi_id' => $kategori->id, 'tarih' => '2026-08-02', 'aciklama' => 'Yeni açıklama', 'notlar' => 'Güncellendi',
        ]);
        $this->assertSame('Yeni açıklama', $guncel->aciklama);
        $this->assertSame('125.00', (string) $guncel->tutar);

        $this->expectException(IsKuraliIstisnasi::class);
        app(MasrafKayitServisi::class)->guncelle($firma->id, $masraf->id, [
            'masraf_kategorisi_id' => $digerKategori->id, 'tarih' => '2026-08-02', 'aciklama' => 'Kaçak',
        ]);
    }

    public function test_masraf_turu_firma_bazli_eklenir_guncellenir_ve_pasiflestirilir(): void
    {
        $firma = $this->firmaOlustur('MASRAF-D');
        $digerFirma = $this->firmaOlustur('MASRAF-E');
        $this->superAdminVeSession($firma);

        MasrafKategorisi::varsayilanlariHazirla($firma->id);
        MasrafKategorisi::varsayilanlariHazirla($digerFirma->id);

        $servis = app(MasrafKategoriServisi::class);
        $kategori = $servis->kaydet($firma->id, [
            'ad' => 'Kargo',
            'sira' => 120,
            'aktif_mi' => true,
        ]);

        $this->assertSame('kargo', $kategori->kod);
        $this->assertDatabaseHas('masraf_kategorileri', [
            'id' => $kategori->id,
            'firma_id' => $firma->id,
            'ad' => 'Kargo',
            'aktif_mi' => 1,
        ]);

        $servis->kaydet($firma->id, [
            'ad' => 'Kargo / Kurye',
            'sira' => 125,
            'aktif_mi' => true,
        ], $kategori->id);
        $pasifKategori = $servis->durumDegistir($firma->id, $kategori->id);

        $this->assertSame('Kargo / Kurye', $pasifKategori->ad);
        $this->assertFalse($pasifKategori->aktif_mi);
        $this->assertSame(0, MasrafKategorisi::query()->where('firma_id', $digerFirma->id)->where('ad', 'Kargo / Kurye')->count());
    }

    public function test_sabit_masraf_hiyerarsisi_ve_firma_alt_kategori_kurali_korunur(): void
    {
        $firma = $this->firmaOlustur('MASRAF-F');
        $this->superAdminVeSession($firma);
        MasrafKategorisi::varsayilanlariHazirla($firma->id);

        $ust = MasrafKategorisi::query()
            ->where('firma_id', $firma->id)
            ->where('kod', 'duzenli_faturalar')
            ->firstOrFail();
        $elektrik = MasrafKategorisi::query()
            ->where('firma_id', $firma->id)
            ->where('kod', 'elektrik')
            ->firstOrFail();

        $this->assertTrue($ust->sistem_mi);
        $this->assertFalse($ust->secilir_mi);
        $this->assertSame($ust->id, $elektrik->ust_kategori_id);
        $this->assertTrue($elektrik->secilir_mi);

        $servis = app(MasrafKategoriServisi::class);
        $firmaUstu = $servis->kaydet($firma->id, ['ad' => 'Seyahat ve Konaklama']);
        $firmaAlti = $servis->kaydet($firma->id, [
            'ad' => 'Konaklama',
            'ust_kategori_id' => $firmaUstu->id,
        ]);

        $firmaUstu->refresh();
        $this->assertFalse($firmaUstu->sistem_mi);
        $this->assertFalse($firmaUstu->secilir_mi);
        $this->assertSame($firmaUstu->id, $firmaAlti->ust_kategori_id);

        $this->expectException(IsKuraliIstisnasi::class);
        $servis->kaydet($firma->id, ['ad' => 'Elektrik Yeni'], $elektrik->id);
    }

    public function test_masraf_kaynak_kaydi_ve_gider_faturasi_dagilimi_duplicate_uretmez(): void
    {
        $firma = $this->firmaOlustur('MASRAF-G');
        $this->superAdminVeSession($firma);
        MasrafKategorisi::varsayilanlariHazirla($firma->id);

        $yakit = MasrafKategorisi::query()->where('firma_id', $firma->id)->where('kod', 'yakit')->firstOrFail();
        $su = MasrafKategorisi::query()->where('firma_id', $firma->id)->where('kod', 'su')->firstOrFail();
        $arac = Arac::query()->create([
            'firma_id' => $firma->id,
            'plaka' => '34 MAS 012',
            'marka' => 'Test',
            'model' => 'Model',
            'aktif_mi' => true,
        ]);
        $kayitServisi = app(MasrafKayitServisi::class);

        $ilkMasraf = $kayitServisi->kaydet($firma->id, [
            'masraf_kategorisi_id' => $yakit->id,
            'kaynak_turu' => 'arac',
            'kaynak_id' => $arac->id,
            'tarih' => Carbon::today()->toDateString(),
            'tutar' => '60.00',
            'para_birimi' => 'TRY',
            'aciklama' => 'Araç yakıtı',
            'yakit_litre' => '20',
            'litre_fiyati' => '45.125',
            'kaynak_kilometre' => 15000,
        ], auth()->id(), 'test:masraf:kaynak:1');
        $ikinciMasraf = $kayitServisi->kaydet($firma->id, [
            'masraf_kategorisi_id' => $su->id,
            'tarih' => Carbon::today()->toDateString(),
            'tutar' => '40.00',
            'para_birimi' => 'TRY',
            'aciklama' => 'Su faturası',
        ], auth()->id(), 'test:masraf:kaynak:2');

        $fatura = Fatura::query()->create([
            'firma_id' => $firma->id,
            'tur' => FaturaTuru::Gider->value,
            'durum' => FaturaDurumu::Onayli->value,
            'tarih' => now(),
            'genel_toplam' => '100.00',
            'odenecek_tutar' => '100.00',
            'para_birimi' => 'TRY',
        ]);

        $servis = app(MasrafFaturaBaglantiServisi::class);
        $ilkDagitim = $servis->bagla($firma->id, $ilkMasraf->id, $fatura->id, '60.00');
        $this->assertSame($ilkDagitim->id, $servis->bagla($firma->id, $ilkMasraf->id, $fatura->id, '60.00')->id);
        $servis->bagla($firma->id, $ikinciMasraf->id, $fatura->id, '40.00');

        $this->assertDatabaseCount('masraf_fatura_dagitilari', 2);
        $this->assertDatabaseHas('masraflar', [
            'id' => $ilkMasraf->id,
            'kaynak_turu' => 'arac',
            'kaynak_id' => $arac->id,
        ]);
        $this->assertDatabaseHas('masraf_arac_detaylari', [
            'masraf_id' => $ilkMasraf->id,
            'arac_id' => $arac->id,
            'yakit_litre' => '20.000',
            'litre_fiyati' => '45.1250',
            'kilometre' => 15000,
        ]);
        $this->assertDatabaseHas('araclar', ['id' => $arac->id, 'kilometre' => 15000]);

        app(FaturaIslemServisi::class)->faturayiIptalEt($fatura);
        $this->assertDatabaseHas('masraflar', [
            'id' => $ilkMasraf->id,
            'durum' => Masraf::DURUM_IPTAL,
            'iptal_nedeni' => 'Bağlı gider faturası iptal edildi. Fatura ID: '.$fatura->id,
        ]);
        $this->assertDatabaseHas('masraflar', [
            'id' => $ikinciMasraf->id,
            'durum' => Masraf::DURUM_IPTAL,
        ]);

        $this->expectException(IsKuraliIstisnasi::class);
        $servis->bagla($firma->id, $ikinciMasraf->id, $fatura->id, '0.01');
    }

    public function test_masraf_formu_yeni_gider_faturasini_onaylayip_tek_kayda_baglar(): void
    {
        $firma = $this->firmaOlustur('MASRAF-H');
        $this->superAdminVeSession($firma);
        MasrafKategorisi::varsayilanlariHazirla($firma->id);
        $kategori = MasrafKategorisi::query()->where('firma_id', $firma->id)->where('kod', 'elektrik')->firstOrFail();
        $cari = Cari::query()->create([
            'firma_id' => $firma->id,
            'ad' => 'Elektrik Tedarikçisi',
            'kod' => 'TED-01',
            'tur' => CariTuru::Tedarikci->value,
            'durum' => CariDurumu::Aktif->value,
        ]);
        $proje = IsletmeProjesi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'MASRAF-H-PRJ',
            'ad' => 'Elektrik proje testi',
            'durum' => IsletmeProjesi::DURUM_AKTIF,
            'para_birimi' => 'TRY',
        ]);

        $masraf = app(MasrafFaturaKayitServisi::class)->kaydet($firma->id, [
            'masraf_kategorisi_id' => $kategori->id,
            'tarih' => Carbon::today()->toDateString(),
            'tutar' => '1250.50',
            'para_birimi' => 'TRY',
            'aciklama' => 'Elektrik faturası',
            'isletme_proje_id' => $proje->id,
        ], 'yeni', [
                'fatura_cari_id' => $cari->id,
                'fatura_vade_tarihi' => Carbon::today()->addDays(15)->toDateString(),
                'kalemler' => [[
                    'satir_no' => 1,
                    'kalem_tipi' => 'hizmet_kalemi',
                    'hizmet_mi' => true,
                    'aciklama' => 'Servis dışı parça ve işçilik',
                    'birim' => 'AD',
                    'miktar' => 1,
                    'birim_fiyat' => '1250.50',
                    'indirim_orani' => 0,
                    'indirim_tutari' => 0,
                    'kdv_orani' => 0,
                    'kdv_tutari' => 0,
                    'para_birimi' => 'TRY',
                ]],
            ], auth()->id(), 'test:masraf:yeni-fatura:1');

        $this->assertDatabaseHas('masraflar', [
            'id' => $masraf->id,
            'tutar' => '1250.50',
        ]);
        $this->assertDatabaseCount('masraf_fatura_dagitilari', 1);
        $this->assertDatabaseHas('faturalar', [
            'tur' => FaturaTuru::Gelen->value,
            'fatura_sinifi' => 'gider',
            'durum' => FaturaDurumu::Onayli->value,
            'cari_id' => $cari->id,
            'genel_toplam' => '1250.50',
            'kaynak_tipi' => 'masraf',
            'isletme_proje_id' => $proje->id,
        ]);
        $this->assertDatabaseHas('fatura_kalemleri', [
            'fatura_id' => DB::table('masraf_fatura_dagitilari')->where('masraf_id', $masraf->id)->value('fatura_id'),
            'aciklama' => 'Servis dışı parça ve işçilik',
        ]);
        $this->assertSame(1, Fatura::query()->where('firma_id', $firma->id)->where('kaynak_tipi', 'masraf')->count());
    }

    public function test_duzenli_fatura_tanimi_masraf_kaynagi_olarak_kullanilir(): void
    {
        $firma = $this->firmaOlustur('MASRAF-I');
        $this->superAdminVeSession($firma);
        MasrafKategorisi::varsayilanlariHazirla($firma->id);
        $elektrik = MasrafKategorisi::query()->where('firma_id', $firma->id)->where('kod', 'elektrik')->firstOrFail();

        $tanim = app(DuzenliFaturaTanimiServisi::class)->kaydet($firma->id, [
            'masraf_kategorisi_id' => $elektrik->id,
            'ad' => 'Merkez ofis elektriği',
            'abone_no' => '123456',
            'tedarikci' => 'Elektrik Dağıtım',
        ]);

        $masraf = app(MasrafKayitServisi::class)->kaydet($firma->id, [
            'masraf_kategorisi_id' => $elektrik->id,
            'kaynak_turu' => 'duzenli_fatura',
            'kaynak_id' => $tanim->id,
            'tarih' => Carbon::today()->toDateString(),
            'tutar' => '850.00',
            'para_birimi' => 'TRY',
            'aciklama' => 'Temmuz elektrik faturası',
        ], auth()->id(), 'test:masraf:duzenli-fatura:1');

        $this->assertDatabaseHas('duzenli_fatura_tanimlari', [
            'id' => $tanim->id,
            'abone_no' => '123456',
        ]);
        $this->assertDatabaseHas('masraflar', [
            'id' => $masraf->id,
            'kaynak_turu' => 'duzenli_fatura',
            'kaynak_id' => $tanim->id,
        ]);
    }

    public function test_masraf_isletme_projesine_baglanir_ve_firma_siniri_korunur(): void
    {
        $firma = $this->firmaOlustur('MASRAF-P');
        $digerFirma = $this->firmaOlustur('MASRAF-Q');
        $this->superAdminVeSession($firma);
        MasrafKategorisi::varsayilanlariHazirla($firma->id);
        MasrafKategorisi::varsayilanlariHazirla($digerFirma->id);

        $kategori = MasrafKategorisi::query()->where('firma_id', $firma->id)->where('kod', 'ofis')->firstOrFail();
        $proje = IsletmeProjesi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'PRJ-001',
            'ad' => 'Yeni kamera kurulumu',
            'durum' => IsletmeProjesi::DURUM_AKTIF,
            'butce_tutari' => '5000.00',
            'para_birimi' => 'TRY',
        ]);
        $digerProje = IsletmeProjesi::withoutGlobalScopes()->create([
            'firma_id' => $digerFirma->id,
            'kod' => 'PRJ-002',
            'ad' => 'Diğer firma projesi',
            'durum' => IsletmeProjesi::DURUM_AKTIF,
            'para_birimi' => 'TRY',
        ]);

        $masraf = app(MasrafKayitServisi::class)->kaydet($firma->id, [
            'masraf_kategorisi_id' => $kategori->id,
            'isletme_proje_id' => $proje->id,
            'tarih' => Carbon::today()->toDateString(),
            'tutar' => '2400.00',
            'para_birimi' => 'TRY',
            'aciklama' => 'Proje ekipman gideri',
        ], auth()->id(), 'test:masraf:proje:1');

        $this->assertSame((int) $proje->id, (int) $masraf->isletme_proje_id);

        $rapor = new MasrafRaporlariSayfasi();
        $rapor->filtreler = [
            'baslangic' => Carbon::today()->subDay()->toDateString(),
            'bitis' => Carbon::today()->addDay()->toDateString(),
            'kategori' => '',
            'isletme_proje_id' => (string) $proje->id,
            'personel_maliyet_turu' => 'brut',
        ];
        $projeRaporu = $rapor->projeButceGerceklesenOzeti();
        $this->assertCount(1, $projeRaporu);
        $this->assertSame('2400.00', $projeRaporu[0]['gerceklesen']);
        $this->assertSame('2600.00', $projeRaporu[0]['kalan']);

        $this->expectException(IsKuraliIstisnasi::class);
        app(MasrafKayitServisi::class)->kaydet($firma->id, [
            'masraf_kategorisi_id' => $kategori->id,
            'isletme_proje_id' => $digerProje->id,
            'tarih' => Carbon::today()->toDateString(),
            'tutar' => '100.00',
            'para_birimi' => 'TRY',
            'aciklama' => 'Firma sınırı testi',
        ], auth()->id(), 'test:masraf:proje:2');
    }

    public function test_kategori_butcesi_raporda_gerceklesen_masrafla_karsilastirilir(): void
    {
        $firma = $this->firmaOlustur('MASRAF-BUTCE');
        $this->superAdminVeSession($firma);
        MasrafKategorisi::varsayilanlariHazirla($firma->id);
        $kategori = MasrafKategorisi::query()->where('firma_id', $firma->id)->where('kod', 'elektrik')->firstOrFail();

        app(MasrafButceServisi::class)->kaydet($firma->id, [
            'masraf_kategorisi_id' => $kategori->id,
            'donem_baslangic' => Carbon::today()->startOfMonth()->toDateString(),
            'donem_bitis' => Carbon::today()->endOfMonth()->toDateString(),
            'butce_tutari' => '5000.00',
            'para_birimi' => 'TRY',
            'durum' => MasrafButcesi::DURUM_AKTIF,
        ]);
        app(MasrafKayitServisi::class)->kaydet($firma->id, [
            'masraf_kategorisi_id' => $kategori->id,
            'tarih' => Carbon::today()->toDateString(),
            'tutar' => '1250.50',
            'para_birimi' => 'TRY',
            'aciklama' => 'Elektrik bütçe testi',
        ], auth()->id(), 'test:masraf:butce:1');

        $rapor = new MasrafRaporlariSayfasi();
        $rapor->filtreler = [
            'baslangic' => Carbon::today()->startOfMonth()->toDateString(),
            'bitis' => Carbon::today()->toDateString(),
            'kategori' => (string) $kategori->id,
            'isletme_proje_id' => '',
            'personel_maliyet_turu' => 'brut',
        ];
        $satirlar = $rapor->kategoriButceGerceklesenOzeti();

        $this->assertCount(1, $satirlar);
        $this->assertSame('1250.50', bcadd((string) DB::table('masraflar')->where('firma_id', $firma->id)->where('masraf_kategorisi_id', $kategori->id)->where('durum', Masraf::DURUM_AKTIF)->whereBetween('tarih', [$rapor->filtreler['baslangic'].' 00:00:00', $rapor->filtreler['bitis'].' 23:59:59'])->where('para_birimi', 'TRY')->sum('tutar'), '0', 2));
        $this->assertSame('5000.00', $satirlar[0]['butce']);
        $this->assertSame('1250.50', $satirlar[0]['gerceklesen']);
        $this->assertSame('3749.50', $satirlar[0]['kalan']);
    }

    public function test_teknik_servis_gider_faturasi_rapora_otomatik_girer_ve_mukerrer_masraf_haric_tutulur(): void
    {
        $firma = $this->firmaOlustur('MASRAF-J');
        $this->superAdminVeSession($firma);
        MasrafKategorisi::varsayilanlariHazirla($firma->id);

        $fatura = Fatura::query()->create([
            'firma_id' => $firma->id,
            'tur' => FaturaTuru::Gider->value,
            'durum' => FaturaDurumu::Onayli->value,
            'tarih' => now(),
            'genel_toplam' => '375.50',
            'odenecek_tutar' => '375.50',
            'para_birimi' => 'TRY',
            'kaynak_tipi' => 'teknik_servis',
            'islem_no' => 901,
        ]);

        $rapor = new MasrafRaporlariSayfasi();
        $rapor->filtreler = [
            'baslangic' => Carbon::today()->subDay()->toDateString(),
            'bitis' => Carbon::today()->addDay()->toDateString(),
            'kategori' => '',
            'personel_maliyet_turu' => 'brut',
        ];

        $satirlar = $rapor->teknikServisGiderOzeti();
        $this->assertCount(1, $satirlar);
        $this->assertSame('375.50', $satirlar[0]['toplam']);

        $kategori = MasrafKategorisi::query()->where('firma_id', $firma->id)->where('kod', 'bakim_onarim')->firstOrFail();
        Masraf::query()->create([
            'firma_id' => $firma->id,
            'masraf_kategorisi_id' => $kategori->id,
            'kaynak_turu' => 'teknik_servis',
            'kaynak_id' => 901,
            'tarih' => Carbon::today(),
            'tutar' => '375.50',
            'para_birimi' => 'TRY',
            'aciklama' => 'Teknik servis gideri',
            'durum' => Masraf::DURUM_AKTIF,
            'idempotency_key' => 'test:masraf:teknik-servis:1',
        ]);

        $this->assertSame([], $rapor->teknikServisGiderOzeti());
    }

    public function test_personel_raporu_isveren_toplamini_ve_maliyet_kirilimini_gosterir(): void
    {
        $firma = $this->firmaOlustur('MASRAF-K');
        $this->superAdminVeSession($firma);

        $personelId = DB::table('personeller')->insertGetId([
            'firma_id' => $firma->id,
            'ad_soyad' => 'Maliyet Personeli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => '1000.00',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $donemId = DB::table('personel_maas_donemleri')->insertGetId([
            'firma_id' => $firma->id,
            'ad' => 'Temmuz 2026',
            'donem_yil' => 2026,
            'donem_ay' => 7,
            'baslangic_tarihi' => Carbon::create(2026, 7, 1)->toDateString(),
            'bitis_tarihi' => Carbon::create(2026, 7, 31)->toDateString(),
            'durum' => 'onaylandi',
            'para_birimi' => 'TRY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('personel_maas_hareketleri')->insert([
            'firma_id' => $firma->id,
            'maas_donemi_id' => $donemId,
            'personel_id' => $personelId,
            'brut_tutar' => '1000.00',
            'fazla_mesai_tutari' => '100.00',
            'prim_tutari' => '50.00',
            'ek_odeme_tutari' => '25.00',
            'net_tutar' => '900.00',
            'sgk_isveren_tutari' => '200.00',
            'issizlik_isveren_tutari' => '20.00',
            'gelir_vergisi_tutari' => '80.00',
            'damga_vergisi_tutari' => '10.00',
            'diger_maliyet_tutari' => '30.00',
            'durum' => 'onaylandi',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rapor = new MasrafRaporlariSayfasi();
        $rapor->filtreler = [
            'baslangic' => '2026-07-01',
            'bitis' => '2026-07-31',
            'kategori' => '',
            'personel_maliyet_turu' => 'isveren_toplam',
        ];

        $satirlar = collect($rapor->personelGiderOzeti())->keyBy('kalem');

        $this->assertSame('1425.00', $satirlar['İşveren toplam maliyeti']['toplam']);
        $this->assertSame('200.00', $satirlar['SGK işveren payı']['toplam']);
        $this->assertSame('80.00', $satirlar['Gelir vergisi']['toplam']);
        $this->assertSame('10.00', $satirlar['Damga vergisi']['toplam']);
        $this->assertSame('30.00', $satirlar['Diğer işveren maliyeti']['toplam']);
    }

    private function firmaOlustur(string $kod): Firma
    {
        return Firma::query()->create([
            'ad' => 'Masraf '.$kod,
            'kisa_ad' => $kod,
            'firma_kodu' => $kod.'-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
    }

    private function superAdminVeSession(Firma $firma): void
    {
        $user = User::query()->create([
            'name' => 'Masraf Test',
            'email' => 'masraf-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => true,
        ]);

        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);
    }

}
