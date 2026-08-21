<?php

namespace Tests\Feature\Restoran;

use App\Filament\Clusters\Restoran\Resources\RestoranAdisyonKaynagi;
use App\Filament\Clusters\Restoran\Resources\RestoranAdisyonKaynagi\RelationManagers\TahsilatlarRelationManager;
use App\Filament\Clusters\Restoran\Resources\RestoranAdisyonKalemiKaynagi;
use App\Filament\Clusters\Restoran\Pages\RestoranGunSonuMutabakatSayfasi;
use App\Filament\Clusters\Restoran\Pages\RestoranRaporlariSayfasi;
use App\Filament\Clusters\Restoran\Pages\RestoranMutfakEkraniSayfasi;
use App\Filament\Clusters\Restoran\Pages\RestoranPaketServisSayfasi;
use App\Filament\Clusters\Restoran\Pages\RestoranMasaEkraniSayfasi;
use App\Filament\Clusters\Restoran\Resources\RestoranMasaKaynagi;
use App\Filament\Clusters\Restoran\Resources\RestoranMenuKategoriKaynagi;
use App\Filament\Clusters\Restoran\Resources\RestoranMenuUrunKaynagi;
use App\Filament\Clusters\Restoran\Resources\RestoranReceteKaynagi;
use App\Filament\Clusters\Restoran\Resources\RestoranSalonKaynagi;
use App\Models\Firma;
use App\Models\Restoran\RestoranAdisyonKalemi;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Restoran\RestoranMasasi;
use App\Models\Restoran\RestoranMenuKategorisi;
use App\Models\Restoran\RestoranMenuUrunu;
use App\Models\Restoran\RestoranSalonu;
use App\Models\Sube;
use App\Models\User;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestoranFilamentUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_restoran_filament_url_yapisi_sabit_kalir(): void
    {
        $beklenenler = [
            [RestoranMasaKaynagi::getUrl(), '/admin/restoran/masalar'],
            [RestoranMasaEkraniSayfasi::getUrl(), '/admin/restoran/masa-ekrani'],
            [RestoranAdisyonKaynagi::getUrl(), '/admin/restoran/adisyonlar'],
            [RestoranAdisyonKalemiKaynagi::getUrl(), '/admin/restoran/adisyon-kalemleri'],
            [RestoranMutfakEkraniSayfasi::getUrl(), '/admin/restoran/mutfak'],
            [RestoranPaketServisSayfasi::getUrl(), '/admin/restoran/paket-servis'],
            [RestoranRaporlariSayfasi::getUrl(), '/admin/restoran/raporlar/genel'],
            [RestoranGunSonuMutabakatSayfasi::getUrl(), '/admin/restoran/raporlar/gun-sonu'],
            [RestoranSalonKaynagi::getUrl(), '/admin/restoran/tanimlar/salonlar'],
            [RestoranMenuKategoriKaynagi::getUrl(), '/admin/restoran/qr-menu/kategoriler'],
            [RestoranMenuUrunKaynagi::getUrl(), '/admin/restoran/qr-menu/urunler'],
            [RestoranReceteKaynagi::getUrl(), '/admin/restoran/qr-menu/receteler'],
        ];

        foreach ($beklenenler as [$url, $path]) {
            $this->assertStringEndsWith($path, parse_url($url, PHP_URL_PATH) ?: $url);
        }
    }

    public function test_restoran_adisyon_tahsilat_gecmisi_yalniz_detay_modunda_relation_olarak_baglanir(): void
    {
        $this->assertNotContains(TahsilatlarRelationManager::class, RestoranAdisyonKaynagi::getRelations());

        request()->query->set('detay', '1');

        try {
            $this->assertContains(TahsilatlarRelationManager::class, RestoranAdisyonKaynagi::getRelations());
        } finally {
            request()->query->remove('detay');
        }
    }

    public function test_restoran_resource_yetkisi_baska_firma_kaydina_uygulanmaz(): void
    {
        $firmaA = $this->firmaOlustur('RFA');
        $firmaB = $this->firmaOlustur('RFB');
        $masaA = RestoranMasasi::withoutGlobalScopes()->create([
            'firma_id' => $firmaA->id,
            'ad' => 'A1',
            'kod' => 'A1',
        ]);
        $masaB = RestoranMasasi::withoutGlobalScopes()->create([
            'firma_id' => $firmaB->id,
            'ad' => 'B1',
            'kod' => 'B1',
        ]);

        $this->actingAs(User::factory()->create([
            'super_admin_mi' => true,
        ]));
        app(TenantContextService::class)->firmaAyarla($firmaA);

        $this->assertTrue(RestoranMasaKaynagi::canView($masaB));
        $this->assertTrue(RestoranMasaKaynagi::canView($masaA));

        $this->actingAs(User::factory()->create());
        app(TenantContextService::class)->firmaAyarla($firmaA);

        $this->assertFalse(RestoranMasaKaynagi::canView($masaB));
        $this->assertFalse(RestoranMasaKaynagi::canEdit($masaB));
    }

    public function test_masa_ekrani_filtreleri_ve_operasyon_ozeti_aktif_firma_ile_sinirlanir(): void
    {
        $firma = $this->firmaOlustur('RMF');
        $digerFirma = $this->firmaOlustur('RMD');
        $sube = $this->subeOlustur($firma, 'A');
        $digerSube = $this->subeOlustur($digerFirma, 'B');
        $salon = RestoranSalonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'ad' => 'Ana Salon',
            'kod' => 'ANA',
        ]);
        $masaBos = RestoranMasasi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'salon_id' => $salon->id,
            'ad' => 'A1',
            'kod' => 'A1',
        ]);
        $masaDolu = RestoranMasasi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'salon_id' => $salon->id,
            'ad' => 'A2',
            'kod' => 'A2',
        ]);
        RestoranMasasi::withoutGlobalScopes()->create([
            'firma_id' => $digerFirma->id,
            'sube_id' => $digerSube->id,
            'ad' => 'B1',
            'kod' => 'B1',
        ]);
        RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'masa_id' => $masaDolu->id,
            'genel_toplam' => 250,
            'durum' => RestoranAdisyonu::DURUM_ACIK,
        ]);

        app(TenantContextService::class)->firmaAyarla($firma);

        $sayfa = app(RestoranMasaEkraniSayfasi::class);
        $this->assertSame(2, $sayfa->masalar()->count());
        $this->assertSame(1, $sayfa->durumSayilari()[RestoranMasasi::DURUM_DOLU]);

        $ozet = $sayfa->operasyonOzeti();
        $this->assertSame(2, $ozet['toplam_masa']);
        $this->assertSame(1, $ozet['acik_adisyon_sayisi']);
        $this->assertSame(250.0, $ozet['acik_adisyon_toplami']);
        $this->assertSame(50.0, $ozet['doluluk_orani']);

        $sayfa->durumFiltresi = RestoranMasasi::DURUM_BOS;
        $this->assertSame([(int) $masaBos->id], $sayfa->masalar()->pluck('id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_masa_ekranindan_adisyon_acilip_siparis_kalemi_eklenir(): void
    {
        $firma = $this->firmaOlustur('RSF');
        $sube = $this->subeOlustur($firma, 'A');
        $masa = RestoranMasasi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'ad' => 'Masa 2',
            'kod' => 'M2',
        ]);
        $kategori = RestoranMenuKategorisi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'ad' => 'Sicaklar',
            'slug' => 'sicaklar',
            'aktif_mi' => true,
        ]);
        $urun = RestoranMenuUrunu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kategori_id' => $kategori->id,
            'ad' => 'Corba',
            'fiyat' => 75,
            'kdv_orani' => 10,
            'aktif_mi' => true,
            'stokta_var_mi' => true,
            'qr_menu_gorunur_mu' => true,
        ]);

        $this->actingAs(User::factory()->create(['super_admin_mi' => true]));
        app(TenantContextService::class)->firmaAyarla($firma);

        $sayfa = app(RestoranMasaEkraniSayfasi::class);
        $sayfa->masaAdisyonuAc((int) $masa->id);

        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->where('masa_id', $masa->id)->firstOrFail();
        $this->assertSame((int) $adisyon->id, $sayfa->urunEkleAdisyonId);
        $this->assertSame(RestoranMasasi::DURUM_DOLU, $masa->refresh()->durum);
        $this->assertSame([(int) $urun->id => 'Sicaklar - Corba'], $sayfa->menuUrunuSecenekleri((int) $adisyon->id));

        $sayfa->siparisFormu = [
            'menu_urunu_id' => (int) $urun->id,
            'miktar' => 2,
            'mutfak_notu' => 'Az tuzlu',
        ];
        $sayfa->siparisKalemiEkle();

        $kalem = RestoranAdisyonKalemi::withoutGlobalScopes()->where('adisyon_id', $adisyon->id)->firstOrFail();
        $this->assertSame('Corba', $kalem->urun_adi);
        $this->assertSame('Az tuzlu', $kalem->mutfak_notu);
        $this->assertSame('2.00000000', (string) $kalem->miktar);
        $this->assertSame('165.00', (string) $adisyon->refresh()->genel_toplam);
        $this->assertNull($sayfa->urunEkleAdisyonId);
    }

    public function test_paket_servis_ekrani_filtreleri_ve_ozeti_aktif_firma_ile_sinirlanir(): void
    {
        $firma = $this->firmaOlustur('RPF');
        $digerFirma = $this->firmaOlustur('RPG');
        $sube = $this->subeOlustur($firma, 'A');
        $digerSube = $this->subeOlustur($digerFirma, 'B');
        $hazirlaniyor = $this->paketAdisyonOlustur($firma, $sube, RestoranAdisyonu::PAKET_DURUM_HAZIRLANIYOR, 120, now()->subMinutes(5));
        $yolda = $this->paketAdisyonOlustur($firma, $sube, RestoranAdisyonu::PAKET_DURUM_YOLDA, 180, now()->subMinutes(10), 'online');
        $teslimEdildi = $this->paketAdisyonOlustur($firma, $sube, RestoranAdisyonu::PAKET_DURUM_TESLIM_EDILDI, 90, now()->subMinutes(60));
        $this->paketAdisyonOlustur($digerFirma, $digerSube, RestoranAdisyonu::PAKET_DURUM_HAZIRLANIYOR, 999, now()->subMinutes(60));

        app(TenantContextService::class)->firmaAyarla($firma);

        $sayfa = app(RestoranPaketServisSayfasi::class);
        $aktifSiparisIdleri = $sayfa->siparisler()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $this->assertSame([(int) $hazirlaniyor->id, (int) $yolda->id], $aktifSiparisIdleri);

        $ozet = $sayfa->durumOzeti();
        $this->assertSame(2, $ozet['toplam']);
        $this->assertSame(1, $ozet['hazirlaniyor']);
        $this->assertSame(1, $ozet['yolda']);
        $this->assertSame(2, $ozet['geciken']);
        $this->assertSame(300.0, $ozet['tutar']);

        $sayfa->paketDurumFiltresi = RestoranAdisyonu::PAKET_DURUM_TESLIM_EDILDI;
        $this->assertSame([(int) $teslimEdildi->id], $sayfa->siparisler()->pluck('id')->map(fn ($id) => (int) $id)->all());

        $sayfa->paketDurumFiltresi = 'aktif';
        $sayfa->siparisTipiFiltresi = 'online';
        $this->assertSame([(int) $yolda->id], $sayfa->siparisler()->pluck('id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_mutfak_ekrani_aktif_kalemleri_durum_kolonlarina_aktif_firma_ile_gruplar(): void
    {
        $firma = $this->firmaOlustur('RKF');
        $digerFirma = $this->firmaOlustur('RKG');
        $sube = $this->subeOlustur($firma, 'A');
        $digerSube = $this->subeOlustur($digerFirma, 'B');

        $adisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'siparis_tipi' => 'masa',
            'durum' => RestoranAdisyonu::DURUM_ACIK,
        ]);
        $digerAdisyon = RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $digerFirma->id,
            'sube_id' => $digerSube->id,
            'siparis_tipi' => 'masa',
            'durum' => RestoranAdisyonu::DURUM_ACIK,
        ]);

        $yeni = $this->mutfakKalemiOlustur($firma, $adisyon, RestoranAdisyonKalemi::DURUM_YENI, 'Corba');
        $hazirlaniyor = $this->mutfakKalemiOlustur($firma, $adisyon, RestoranAdisyonKalemi::DURUM_HAZIRLANIYOR, 'Ana Yemek');
        $hazir = $this->mutfakKalemiOlustur($firma, $adisyon, RestoranAdisyonKalemi::DURUM_HAZIR, 'Tatli');
        $this->mutfakKalemiOlustur($digerFirma, $digerAdisyon, RestoranAdisyonKalemi::DURUM_YENI, 'Firma Disi');

        app(TenantContextService::class)->firmaAyarla($firma);

        $sayfa = app(RestoranMutfakEkraniSayfasi::class);
        $gruplar = $sayfa->kalemGruplari();

        $this->assertSame([(int) $yeni->id], $gruplar[RestoranAdisyonKalemi::DURUM_YENI]->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([(int) $hazirlaniyor->id], $gruplar[RestoranAdisyonKalemi::DURUM_HAZIRLANIYOR]->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([(int) $hazir->id], $gruplar[RestoranAdisyonKalemi::DURUM_HAZIR]->pluck('id')->map(fn ($id) => (int) $id)->all());
    }

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

    private function subeOlustur(Firma $firma, string $kod): Sube
    {
        return Sube::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad' => 'Şube '.$kod,
            'kod' => $kod,
            'aktif_mi' => true,
        ]);
    }

    private function paketAdisyonOlustur(
        Firma $firma,
        Sube $sube,
        string $paketDurum,
        float $tutar,
        mixed $tahminiTeslimat,
        string $siparisTipi = 'paket'
    ): RestoranAdisyonu {
        return RestoranAdisyonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube->id,
            'siparis_tipi' => $siparisTipi,
            'paket_durum' => $paketDurum,
            'tahmini_teslimat_at' => $tahminiTeslimat,
            'genel_toplam' => $tutar,
            'durum' => RestoranAdisyonu::DURUM_ACIK,
        ]);
    }

    private function mutfakKalemiOlustur(
        Firma $firma,
        RestoranAdisyonu $adisyon,
        string $durum,
        string $urunAdi
    ): RestoranAdisyonKalemi {
        return RestoranAdisyonKalemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'adisyon_id' => $adisyon->id,
            'urun_adi' => $urunAdi,
            'miktar' => 1,
            'birim_fiyat' => 100,
            'ara_toplam' => 100,
            'genel_toplam' => 100,
            'durum' => $durum,
        ]);
    }
}
