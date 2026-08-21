<?php

namespace Tests\Feature\MasrafTakip;

use App\Filament\Clusters\MasrafTakip;
use App\Filament\Clusters\MasrafTakip\Pages\MasrafTakibiSayfasi;
use App\Filament\Clusters\MasrafTakip\Pages\MasrafKategorileriSayfasi;
use App\Filament\Clusters\MasrafTakip\Pages\MasrafRaporlariSayfasi;
use App\Filament\Clusters\MasrafTakip\Pages\AraclarSayfasi;
use App\Filament\Clusters\MasrafTakip\Pages\DuzenliFaturaTanimlariSayfasi;
use App\Filament\Clusters\MasrafTakip\Pages\IsletmeProjeleriSayfasi;
use App\Filament\Clusters\MasrafTakip\Pages\MasrafButceleriSayfasi;
use App\Filament\Clusters\ProjeYonetimi\Pages\IsletmeProjeleriSayfasi as ProjeYonetimiIsletmeProjeleriSayfasi;
use App\Filament\Clusters\ProjeYonetimi\Pages\ProjeRaporlariSayfasi;
use App\Models\Firma;
use App\Models\Muhasebe\Masraf;
use App\Models\Muhasebe\MasrafKategorisi;
use App\Models\Proje\IsletmeProjesi;
use App\Models\User;
use App\Support\MasrafTakipYetkiSablonlari;
use Database\Seeders\SaasModulesSeeder;
use Database\Seeders\SaasPermissionsSeeder;
use Database\Seeders\SaasPlanModuleMatrixSeeder;
use Database\Seeders\SaasPlansSeeder;
use Database\Seeders\SaasRolePermissionMatrixSeeder;
use Database\Seeders\SaasRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MasrafTakipModulErisimTest extends TestCase
{
    use RefreshDatabase;

    public function test_masraf_takibi_bagimsiz_cluster_route_ve_yetki_kodlarini_kullanir(): void
    {
        $urlYolu = parse_url(MasrafTakibiSayfasi::getUrl(), PHP_URL_PATH) ?: '';

        $this->assertSame(MasrafTakip::class, MasrafTakibiSayfasi::getCluster());
        $this->assertStringEndsWith('/admin/masraf-takip/masraflar', $urlYolu);
        $this->assertStringEndsWith('/admin/masraf-takip/tanimlar/masraf-turleri', parse_url(MasrafKategorileriSayfasi::getUrl(), PHP_URL_PATH) ?: '');
        $this->assertStringEndsWith('/admin/masraf-takip/raporlar', parse_url(MasrafRaporlariSayfasi::getUrl(), PHP_URL_PATH) ?: '');
        $this->assertStringEndsWith('/admin/masraf-takip/tanimlar/araclar', parse_url(AraclarSayfasi::getUrl(), PHP_URL_PATH) ?: '');
        $this->assertStringEndsWith('/admin/masraf-takip/tanimlar/duzenli-faturalar', parse_url(DuzenliFaturaTanimlariSayfasi::getUrl(), PHP_URL_PATH) ?: '');
        $this->assertStringEndsWith('/admin/masraf-takip/tanimlar/projeler', parse_url(IsletmeProjeleriSayfasi::getUrl(), PHP_URL_PATH) ?: '');
        $this->assertStringEndsWith('/admin/proje-yonetimi/projeler', parse_url(ProjeYonetimiIsletmeProjeleriSayfasi::getUrl(), PHP_URL_PATH) ?: '');
        $this->assertStringEndsWith('/admin/proje-yonetimi/raporlar', parse_url(ProjeRaporlariSayfasi::getUrl(), PHP_URL_PATH) ?: '');
        $this->assertStringEndsWith('/admin/masraf-takip/tanimlar/butceler', parse_url(MasrafButceleriSayfasi::getUrl(), PHP_URL_PATH) ?: '');
        $this->assertSame('masraf_takip.goruntule', MasrafTakipYetkiSablonlari::GORUNTULE);
        $this->assertSame('masraf_takip.olustur', MasrafTakipYetkiSablonlari::OLUSTUR);
        $this->assertSame('masraf_takip.guncelle', MasrafTakipYetkiSablonlari::GUNCELLE);
        $this->assertSame('masraf_takip.sil', MasrafTakipYetkiSablonlari::SIL);
    }

    public function test_masraf_takip_modulu_plan_izin_ve_rol_matrisine_seed_edilir(): void
    {
        $this->seed(SaasModulesSeeder::class);
        $this->seed(SaasPlansSeeder::class);
        $this->seed(SaasPermissionsSeeder::class);
        $this->seed(SaasRolesSeeder::class);
        $this->seed(SaasRolePermissionMatrixSeeder::class);
        $this->seed(SaasPlanModuleMatrixSeeder::class);

        $this->assertDatabaseHas('moduller', ['kod' => 'masraf_takip']);
        $this->assertDatabaseHas('yetkiler', ['kod' => MasrafTakipYetkiSablonlari::GORUNTULE]);
        $this->assertDatabaseHas('yetkiler', ['kod' => MasrafTakipYetkiSablonlari::OLUSTUR]);
        $this->assertDatabaseHas('plan_modulleri', [
            'modul_id' => (int) \App\Models\Modul::query()->where('kod', 'masraf_takip')->value('id'),
        ]);
    }

    public function test_proje_rapor_ozeti_para_birimlerini_birlestirmez(): void
    {
        $sayfa = new ProjeRaporlariSayfasi();

        $ozetler = $sayfa->raporOzetleri([
            [
                'para_birimi' => 'TRY', 'butce' => '150000.00', 'masraf' => '80000.00',
                'gelir' => '120000.00', 'odeme' => '50000.00', 'net' => '70000.00', 'kalan' => '70000.00',
            ],
            [
                'para_birimi' => 'TRY', 'butce' => '50000.00', 'masraf' => '10000.00',
                'gelir' => '25000.00', 'odeme' => '5000.00', 'net' => '20000.00', 'kalan' => '40000.00',
            ],
            [
                'para_birimi' => 'EUR', 'butce' => '20000.00', 'masraf' => '3000.00',
                'gelir' => '0.00', 'odeme' => '1000.00', 'net' => '-4000.00', 'kalan' => '17000.00',
            ],
        ]);

        $this->assertCount(2, $ozetler);
        $this->assertSame([
            'para_birimi' => 'TRY', 'butce' => '200000.00', 'masraf' => '90000.00',
            'gelir' => '145000.00', 'odeme' => '55000.00', 'net' => '90000.00', 'kar' => '55000.00', 'kar_marji' => '37.931034482759', 'kalan' => '110000.00',
        ], $ozetler[0]);
        $this->assertSame('EUR', $ozetler[1]['para_birimi']);
        $this->assertSame('20000.00', $ozetler[1]['butce']);
        $this->assertSame('3000.00', $ozetler[1]['masraf']);
    }

    public function test_proje_gorunurlugu_atamaya_gore_filtrelenir(): void
    {
        $firma = Firma::query()->create([
            'ad' => 'Görünürlük Test Firması',
            'kisa_ad' => 'GTF',
            'firma_kodu' => 'GTF-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
        $kullanici = User::factory()->create();
        $digerKullanici = User::factory()->create();

        $atanmamis = IsletmeProjesi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id, 'kod' => 'PRJ-GENEL', 'ad' => 'Genel proje', 'durum' => IsletmeProjesi::DURUM_AKTIF,
            'para_birimi' => 'TRY',
        ]);
        $atanmis = IsletmeProjesi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id, 'kod' => 'PRJ-ATANAN', 'ad' => 'Atanan proje', 'durum' => IsletmeProjesi::DURUM_AKTIF,
            'para_birimi' => 'TRY',
        ]);
        $atanmis->kullanicilar()->attach($kullanici->id);

        $this->actingAs($kullanici);
        $gorunenler = IsletmeProjesi::withoutGlobalScopes()
            ->kullaniciIcinGorunur(null, (int) $firma->id)
            ->pluck('kod')->all();
        $this->assertEqualsCanonicalizing(['PRJ-GENEL', 'PRJ-ATANAN'], $gorunenler);

        $this->actingAs($digerKullanici);
        $digerGorunenler = IsletmeProjesi::withoutGlobalScopes()
            ->kullaniciIcinGorunur(null, (int) $firma->id)
            ->pluck('kod')->all();
        $this->assertSame(['PRJ-GENEL'], $digerGorunenler);
    }

    public function test_proje_hareketleri_sadece_proje_baglantili_kaydi_listeler(): void
    {
        $firma = Firma::query()->create([
            'ad' => 'Hareket Test Firması',
            'kisa_ad' => 'HTF',
            'firma_kodu' => 'HTF-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
        $kullanici = User::factory()->create();
        $proje = IsletmeProjesi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kod' => 'PRJ-HAREKET',
            'ad' => 'Hareket projesi',
            'durum' => IsletmeProjesi::DURUM_AKTIF,
            'para_birimi' => 'TRY',
        ]);
        $kategori = MasrafKategorisi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kod' => 'test',
            'ad' => 'Test',
            'sira' => 1,
            'sistem_mi' => false,
            'secilir_mi' => true,
            'aktif_mi' => true,
        ]);

        $this->actingAs($kullanici);
        app(\App\Services\TenantContextService::class)->firmaAyarla($firma);

        Masraf::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'masraf_kategorisi_id' => $kategori->id,
            'isletme_proje_id' => $proje->id,
            'tarih' => Carbon::today()->subDay()->toDateString(),
            'tutar' => 1250,
            'para_birimi' => 'TRY',
            'aciklama' => 'Proje bağlantılı test masrafı',
            'durum' => 'aktif',
            'idempotency_key' => 'test-hareket-'.uniqid(),
        ]);

        $sayfa = new ProjeRaporlariSayfasi;
        $sayfa->filtreler = [
            'baslangic' => Carbon::today()->subDays(2)->toDateString(),
            'bitis' => Carbon::today()->toDateString(),
            'proje_id' => '',
            'durum' => 'aktif',
        ];

        $hareketler = $sayfa->projeHareketleri();

        $this->assertSame(1, $hareketler->total());
        $this->assertSame('Masraf', $hareketler->items()[0]->hareket_turu);
        $this->assertSame('PRJ-HAREKET', $hareketler->items()[0]->proje_kodu);
    }
}
