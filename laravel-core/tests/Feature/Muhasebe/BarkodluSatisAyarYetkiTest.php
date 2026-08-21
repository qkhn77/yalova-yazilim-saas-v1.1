<?php

namespace Tests\Feature\Muhasebe;

use App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisIadeGecmisiSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisAyarlarSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisSayfasi;
use App\Models\Firma;
use App\Models\FirmaKullanici;
use App\Models\FirmaModulu;
use App\Models\Modul;
use App\Models\Rol;
use App\Models\User;
use App\Models\Yetki;
use App\Services\FirmaAyarDeposu;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BarkodluSatisAyarYetkiTest extends TestCase
{
    use RefreshDatabase;

    public function test_ayar_sayfasi_eski_barkod_yetkileriyle_acilmaz(): void
    {
        [$user, $firma] = $this->kullaniciHazirla('eski-yetki', [
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_GORUNTULE,
        ]);

        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $this->assertFalse(BarkodluSatisAyarlarSayfasi::canAccess());
    }

    public function test_ayar_goruntule_yetkisi_sayfaya_erisim_verir(): void
    {
        [$user, $firma] = $this->kullaniciHazirla('ayar-gor', [
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_AYAR_GORUNTULE,
        ]);

        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $this->assertTrue(BarkodluSatisAyarlarSayfasi::canAccess());
    }

    public function test_ayar_guncelle_yetkisi_yoksa_kaydet_403_verir(): void
    {
        [$user, $firma] = $this->kullaniciHazirla('ayar-kaydet-yok', [
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_AYAR_GORUNTULE,
        ]);

        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $sayfa = app(BarkodluSatisAyarlarSayfasi::class);
        $sayfa->mount();
        $sayfa->data['barkodlu_iade_geri_alma_suresi_saniye'] = 12;

        try {
            $sayfa->kaydet();
            $this->fail('Kaydet islemi 403 vermeliydi.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_ayar_guncelle_yetkisi_ile_kaydet_yapilir(): void
    {
        [$user, $firma] = $this->kullaniciHazirla('ayar-kaydet-var', [
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_AYAR_GUNCELLE,
        ]);

        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $sayfa = app(BarkodluSatisAyarlarSayfasi::class);
        $sayfa->mount();
        $sayfa->data['barkodlu_iade_geri_alma_suresi_saniye'] = 14;
        $sayfa->data['barkodlu_satis_eksi_stok_izinli'] = true;
        $sayfa->data['barkodlu_satis_varsayilan_odeme_tipi'] = 'kart';
        $sayfa->data['barkodlu_satis_iade_ultra_hizli_varsayilan'] = false;
        $sayfa->kaydet();

        $this->assertSame(
            14,
            (int) app(FirmaAyarDeposu::class)->oku((int) $firma->id, 'barkodlu_iade_geri_alma_suresi_saniye', 5)
        );
        $this->assertTrue(
            (bool) app(FirmaAyarDeposu::class)->oku((int) $firma->id, 'barkodlu_satis_eksi_stok_izinli', false)
        );
        $this->assertSame(
            'kart',
            (string) app(FirmaAyarDeposu::class)->oku((int) $firma->id, 'barkodlu_satis_varsayilan_odeme_tipi', 'nakit')
        );
        $this->assertFalse(
            (bool) app(FirmaAyarDeposu::class)->oku((int) $firma->id, 'barkodlu_satis_iade_ultra_hizli_varsayilan', true)
        );
    }

    public function test_pos_ve_iade_ekrani_varsayilanlari_ayarlardan_okur(): void
    {
        [$user, $firma] = $this->kullaniciHazirla('ayar-varsayilan', [
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_AYAR_GUNCELLE,
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_GORUNTULE,
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_IADE,
        ]);

        app(FirmaAyarDeposu::class)->yaz((int) $firma->id, 'barkodlu_satis_varsayilan_odeme_tipi', 'kart');
        app(FirmaAyarDeposu::class)->yaz((int) $firma->id, 'barkodlu_satis_iade_ultra_hizli_varsayilan', false);

        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $satisSayfasi = app(BarkodluSatisSayfasi::class);
        $satisSayfasi->mount();
        $this->assertSame('kart', (string) ($satisSayfasi->data['odeme_tipi'] ?? 'nakit'));

        $iadeSayfasi = app(BarkodluSatisIadeGecmisiSayfasi::class);
        $iadeSayfasi->mount();
        $this->assertFalse((bool) ($iadeSayfasi->hizliIade['tek_kalem_otomatik_kaydet'] ?? true));
    }

    /**
     * @param  list<string>  $yetkiKodlari
     * @return array{0: User, 1: Firma}
     */
    private function kullaniciHazirla(string $kod, array $yetkiKodlari): array
    {
        $firma = Firma::query()->create([
            'ad' => 'Test '.$kod,
            'kisa_ad' => substr(strtoupper($kod), 0, 10),
            'firma_kodu' => strtoupper($kod).'-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);

        $modul = Modul::query()->firstOrCreate(
            ['kod' => 'barkodlu_satis'],
            ['ad' => 'Barkodlu Satis', 'aciklama' => null, 'aktif_mi' => true, 'siralama' => 30]
        );
        FirmaModulu::query()->create([
            'firma_id' => $firma->id,
            'modul_id' => $modul->id,
            'durum' => 'aktif',
        ]);

        $yetkiIdleri = [];
        foreach ($yetkiKodlari as $yetkiKodu) {
            $yetki = Yetki::query()->firstOrCreate(
                ['kod' => $yetkiKodu],
                [
                    'ad' => strtoupper($yetkiKodu),
                    'modul_kodu' => str_starts_with($yetkiKodu, 'barkodlu_satis_ayar') ? 'barkodlu_satis' : 'barkodlu_satis',
                    'eylem' => str_ends_with($yetkiKodu, '.guncelle') ? 'guncelle' : 'goruntule',
                ]
            );
            $yetkiIdleri[] = (int) $yetki->id;
        }

        $rol = Rol::query()->create([
            'ad' => 'Rol '.$kod,
            'kod' => 'rol-'.$kod.'-'.uniqid(),
            'aciklama' => null,
            'sistem_rolu_mu' => false,
        ]);
        $rol->yetkiler()->attach($yetkiIdleri);

        $user = User::query()->create([
            'name' => 'User '.$kod,
            'email' => 'user-'.$kod.'-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => false,
        ]);

        FirmaKullanici::query()->withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kullanici_id' => $user->id,
            'rol_id' => $rol->id,
            'durum' => 'aktif',
            'varsayilan_firma_mi' => true,
        ]);

        return [$user, $firma];
    }
}
