<?php

namespace Tests\Feature\Muhasebe;

use App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisGecmisiSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\VadeTakipSayfasi;
use App\Filament\Clusters\Muhasebe\Resources\CariKartiKaynagi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi;
use App\Models\Firma;
use App\Models\FirmaKullanici;
use App\Models\FirmaModulu;
use App\Models\Modul;
use App\Models\Muhasebe\Cari;
use App\Models\TeknikServis\TeknikServisDurumTanimi;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Models\User;
use App\Muhasebe\Servisler\AlacakPlanServisi;
use App\Services\TenantContextService;
use App\TeknikServis\Enumlar\ServisTipi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class VadeTakipUiSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_vade_entegre_kritik_filament_sayfalari_acilir(): void
    {
        [$user, $firma, $cari, $servis] = $this->senaryoHazirla();

        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $this->assertTrue(VadeTakipSayfasi::canAccess());
        $this->assertTrue(BarkodluSatisGecmisiSayfasi::canAccess());
        $this->assertTrue(CariKartiKaynagi::canViewAny());
        $this->assertTrue(TeknikServisKaydiKaynagi::canViewAny());
        $this->assertTrue(Route::has('filament.admin.muhasebe.pages.finans.vade-takibi'));

        $sayfalar = [
            [VadeTakipSayfasi::getUrl(), '/admin/muhasebe/finans/vade-takibi'],
            [BarkodluSatisGecmisiSayfasi::getUrl(), '/admin/muhasebe/satis/barkodlu-satis-gecmisi'],
            [CariKartiKaynagi::getUrl('view', ['record' => $cari]), '/admin/muhasebe/cari-yonetimi/cariler/'.$cari->getKey()],
            [TeknikServisKaydiKaynagi::getUrl('edit', ['record' => $servis]), '/admin/teknik-servis/servis-kayitlari/'.$servis->getKey().'/duzenle'],
        ];

        foreach ($sayfalar as [$url, $beklenenYol]) {
            $yol = $this->yerelYol((string) $url);

            $this->assertSame($beklenenYol, $yol);
        }

    }

    /**
     * @return array{0:User,1:Firma,2:Cari,3:TeknikServisKaydi}
     */
    private function senaryoHazirla(): array
    {
        $firma = Firma::query()->create([
            'ad' => 'UI Smoke Firma',
            'kisa_ad' => 'UISMOKE',
            'firma_kodu' => 'UIS-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);

        foreach (['muhasebe' => 'Muhasebe', 'teknik_servis' => 'Teknik Servis'] as $kod => $ad) {
            $modul = Modul::query()->firstOrCreate(
                ['kod' => $kod],
                ['ad' => $ad, 'aciklama' => null, 'aktif_mi' => true, 'siralama' => 1]
            );
            FirmaModulu::query()->create([
                'firma_id' => $firma->id,
                'modul_id' => $modul->id,
                'durum' => 'aktif',
            ]);
        }

        $user = User::query()->create([
            'name' => 'UI Smoke User',
            'email' => 'ui-smoke-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => true,
        ]);

        FirmaKullanici::query()->withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kullanici_id' => $user->id,
            'rol_id' => null,
            'durum' => 'aktif',
            'varsayilan_firma_mi' => true,
        ]);
        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $cari = Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'UI-CARI-'.uniqid(),
            'ad' => 'UI Smoke Cari',
            'tur' => 'musteri',
            'durum' => 'aktif',
            'para_birimi' => 'TRY',
            'gsm' => '05321234567',
            'email' => 'ui-smoke-cari@test.local',
        ]);

        $durum = TeknikServisDurumTanimi::query()->create([
            'firma_id' => null,
            'ad' => 'Açık',
            'kod' => 'acik',
            'aktif' => true,
            'siralama' => 1,
            'varsayilan_mi' => false,
            'is_fiyat_verildi' => false,
            'is_teslim_edildi' => false,
            'is_iptal' => false,
            'is_iade' => false,
        ]);

        $servis = TeknikServisKaydi::query()->create([
            'firma_id' => $firma->id,
            'servis_tipi' => ServisTipi::ArizaliCihaz->value,
            'oncelik' => 'normal',
            'servis_kanali' => 'magaza',
            'cari_id' => $cari->id,
            'musteri_sikayeti' => 'UI smoke servis kaydi',
            'kabul_tarihi' => now(),
            'fis_no' => 'UI-SER'.random_int(1000, 9999),
            'musteri_onay_durumu' => 'beklemede',
            'servis_durumu_id' => $durum->id,
            'toplam_tutar' => '120.00',
            'odenen_tutar' => '0.00',
            'odeme_durumu' => 'odenmedi',
            'olusturan_id' => $user->id,
        ]);

        app(AlacakPlanServisi::class)->teknikServisIcinOlustur($servis->fresh(['cari']) ?? $servis, [
            'plan_turu' => 'taksit',
            'toplam_tutar' => '120.00',
            'pesinat_tutari' => '0.00',
            'para_birimi' => 'TRY',
            'ilk_vade_tarihi' => now()->addDays(10)->toDateString(),
            'taksit_sayisi' => 3,
            'taksit_araligi_gun' => 30,
        ]);

        return [$user, $firma, $cari, $servis];
    }

    private function yerelYol(string $url): string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: $url);
        $query = (string) (parse_url($url, PHP_URL_QUERY) ?: '');
        $prefix = (string) (parse_url((string) config('app.url'), PHP_URL_PATH) ?: '');

        if ($prefix !== '' && $prefix !== '/') {
            $path = preg_replace('#^'.preg_quote(rtrim($prefix, '/'), '#').'#', '', $path) ?: $path;
        }

        return $query !== '' ? $path.'?'.$query : $path;
    }
}
