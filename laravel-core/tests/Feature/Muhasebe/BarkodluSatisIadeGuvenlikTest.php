<?php

namespace Tests\Feature\Muhasebe;

use App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisIadeFisiSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisIadeGecmisiSayfasi;
use App\Models\Firma;
use App\Models\Muhasebe\BarkodluSatis;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\StokKarti;
use App\Models\User;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Muhasebe\Servisler\BarkodluSatisServisi;
use App\Services\FirmaAyarDeposu;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Tests\TestCase;

class BarkodluSatisIadeGuvenlikTest extends TestCase
{
    use RefreshDatabase;

    public function test_firma_disi_stok_ile_satis_tamamlanamaz(): void
    {
        [$user, $firmaA] = $this->superAdminVeFirmaSession('FDS-A');
        [, $firmaB] = $this->superAdminVeFirmaSession('FDS-B');

        $stokB = $this->stokOlustur($firmaB, [
            'stok_takip' => true,
            'stok_miktari' => '10.0000',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('firma disi veya silinmis stok');

        app(BarkodluSatisServisi::class)->satisTamamla((int) $firmaA->id, (int) $user->id, [
            'satis_tarihi' => now()->toDateTimeString(),
            'odeme_tipi' => 'nakit',
            'para_birimi' => 'TRY',
            'kalemler' => [[
                'stok_id' => (int) $stokB->id,
                'miktar' => 1,
                'birim_fiyat' => 100,
                'kdv_orani' => 20,
            ]],
        ]);
    }

    public function test_yetersiz_stokta_satis_tamamlanamaz(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('YST');
        $stok = $this->stokOlustur($firma, [
            'stok_takip' => true,
            'stok_miktari' => '1.0000',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Yetersiz stok');

        app(BarkodluSatisServisi::class)->satisTamamla((int) $firma->id, (int) $user->id, [
            'satis_tarihi' => now()->toDateTimeString(),
            'odeme_tipi' => 'nakit',
            'para_birimi' => 'TRY',
            'kalemler' => [[
                'stok_id' => (int) $stok->id,
                'miktar' => 2,
                'birim_fiyat' => 100,
                'kdv_orani' => 20,
            ]],
        ]);
    }

    public function test_eksi_stok_izinli_ise_yetersiz_stokta_satis_tamamlanir(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('EKS-IZN');
        $stok = $this->stokOlustur($firma, [
            'stok_takip' => true,
            'stok_miktari' => '1.0000',
        ]);

        $satis = app(BarkodluSatisServisi::class)->satisTamamla((int) $firma->id, (int) $user->id, [
            'satis_tarihi' => now()->toDateTimeString(),
            'odeme_tipi' => 'nakit',
            'para_birimi' => 'TRY',
            'eksi_stok_izinli' => true,
            'kalemler' => [[
                'stok_id' => (int) $stok->id,
                'miktar' => 2,
                'birim_fiyat' => 100,
                'kdv_orani' => 20,
            ]],
        ]);

        $this->assertNotNull($satis->id);
        $stok->refresh();
        $this->assertSame('-1.0000', number_format((float) $stok->stok_miktari, 4, '.', ''));
    }

    public function test_kalem_miktarlari_gecersizse_satis_kaydi_olusturulmaz(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('KMG');
        $stok = $this->stokOlustur($firma, [
            'stok_takip' => true,
            'stok_miktari' => '50.0000',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('en az bir gecerli kalem');

        app(BarkodluSatisServisi::class)->satisTamamla((int) $firma->id, (int) $user->id, [
            'satis_tarihi' => now()->toDateTimeString(),
            'odeme_tipi' => 'nakit',
            'para_birimi' => 'TRY',
            'kalemler' => [[
                'stok_id' => (int) $stok->id,
                'miktar' => 0,
                'birim_fiyat' => 100,
                'kdv_orani' => 20,
            ]],
        ]);
    }

    public function test_ayni_stok_coklu_satir_toplami_stoktan_fazlaysa_satisa_izin_verilmez(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('KILIT');
        $stok = $this->stokOlustur($firma, [
            'stok_takip' => true,
            'stok_miktari' => '5.0000',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Yetersiz stok');

        app(BarkodluSatisServisi::class)->satisTamamla((int) $firma->id, (int) $user->id, [
            'satis_tarihi' => now()->toDateTimeString(),
            'odeme_tipi' => 'nakit',
            'para_birimi' => 'TRY',
            'kalemler' => [
                [
                    'stok_id' => (int) $stok->id,
                    'miktar' => 3,
                    'birim_fiyat' => 100,
                    'kdv_orani' => 20,
                ],
                [
                    'stok_id' => (int) $stok->id,
                    'miktar' => 3,
                    'birim_fiyat' => 100,
                    'kdv_orani' => 20,
                ],
            ],
        ]);
    }

    public function test_iptal_edilen_satis_ikinci_kez_iptal_edilemez(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('ISE');
        $stok = $this->stokOlustur($firma, [
            'stok_takip' => true,
            'stok_miktari' => '10.0000',
        ]);

        $satis = $this->satisOlustur($firma, $user, $stok, 1);
        app(BarkodluSatisServisi::class)->satisIptalEt((int) $firma->id, (int) $satis->id, (int) $user->id, 'test iptal');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('daha once iptal');

        app(BarkodluSatisServisi::class)->satisIptalEt((int) $firma->id, (int) $satis->id, (int) $user->id, 'tekrar');
    }

    public function test_iade_miktari_kalan_miktari_gecemez(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('IMK');
        $stok = $this->stokOlustur($firma, [
            'stok_takip' => true,
            'stok_miktari' => '20.0000',
        ]);

        $satis = $this->satisOlustur($firma, $user, $stok, 2);
        $kalemId = (int) $satis->kalemler()->value('id');

        app(BarkodluSatisServisi::class)->satisKalemiIadeEt((int) $firma->id, (int) $satis->id, $kalemId, 1, (int) $user->id, 'kismi');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('kalan miktardan fazla');

        app(BarkodluSatisServisi::class)->satisKalemiIadeEt((int) $firma->id, (int) $satis->id, $kalemId, 1.1, (int) $user->id, 'fazla');
    }

    public function test_iptal_edilmis_satisa_iade_yapilamaz(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('IIS');
        $stok = $this->stokOlustur($firma, [
            'stok_takip' => true,
            'stok_miktari' => '20.0000',
        ]);

        $satis = $this->satisOlustur($firma, $user, $stok, 2);
        $kalemId = (int) $satis->kalemler()->value('id');
        app(BarkodluSatisServisi::class)->satisIptalEt((int) $firma->id, (int) $satis->id, (int) $user->id, 'iptal');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Iptal edilen satis');

        app(BarkodluSatisServisi::class)->satisKalemiIadeEt((int) $firma->id, (int) $satis->id, $kalemId, 1, (int) $user->id, null);
    }

    public function test_iade_fisi_dogrulama_kod_ve_imza_ile_calismalidir(): void
    {
        config(['app.key' => 'base64:VEVTVF9CUkNPRE9fU0FUSVNfSUFERV9LRVk=']);

        [$user, $firma] = $this->superAdminVeFirmaSession('SIG');
        $stok = $this->stokOlustur($firma, [
            'stok_takip' => true,
            'stok_miktari' => '10.0000',
        ]);

        $satis = $this->satisOlustur($firma, $user, $stok, 1);
        $kalemId = (int) $satis->kalemler()->value('id');
        $iade = app(BarkodluSatisServisi::class)->satisKalemiIadeEt((int) $firma->id, (int) $satis->id, $kalemId, 1, (int) $user->id, 'tam');

        $kod = (string) $iade->dogrulama_kodu;
        $sig = $this->iadeFisiImzasi((int) $iade->id, $kod);

        app()->instance('request', Request::create('/test', 'GET', [
            'iade' => (int) $iade->id,
            'kod' => $kod,
            'sig' => $sig,
        ]));
        $sayfa = app(BarkodluSatisIadeFisiSayfasi::class);
        $sayfa->mount();
        $this->assertTrue($sayfa->dogrulamaBasarili);

        app()->instance('request', Request::create('/test', 'GET', [
            'iade' => (int) $iade->id,
            'kod' => $kod,
            'sig' => 'gecersiz-imza',
        ]));
        $sayfa = app(BarkodluSatisIadeFisiSayfasi::class);
        $sayfa->mount();
        $this->assertFalse($sayfa->dogrulamaBasarili);
    }

    public function test_hizli_iade_akisi_satis_no_ile_iade_kaydi_olusturur(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('HIZ-IAD');
        $stok = $this->stokOlustur($firma, [
            'stok_takip' => true,
            'stok_miktari' => '10.0000',
        ]);

        $satis = $this->satisOlustur($firma, $user, $stok, 2);

        $sayfa = app(BarkodluSatisIadeGecmisiSayfasi::class);
        $sayfa->mount();
        $sayfa->hizliIade['satis_no'] = (string) $satis->satis_no;
        $sayfa->hizliIadeSatisiniYukle();

        $this->assertSame((int) $satis->id, (int) ($sayfa->hizliIadeSatisId ?? 0));
        $this->assertNotNull($sayfa->hizliIade['satis_kalem_id'] ?? null);
        $this->assertSame(1.0, (float) ($sayfa->hizliIade['iade_miktari'] ?? 0));

        $sayfa->hizliIade['iade_miktari'] = 1;
        $sayfa->hizliIade['neden'] = 'hizli iade test';
        $sayfa->hizliIadeKaydet();

        $this->assertDatabaseHas('muhasebe_barkodlu_satis_iadeler', [
            'firma_id' => (int) $firma->id,
            'satis_id' => (int) $satis->id,
            'neden' => 'hizli iade test',
        ]);
    }

    public function test_hizli_iade_tek_kalemde_otomatik_kayit_yapar(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('AUTO-IAD');
        $stok = $this->stokOlustur($firma, [
            'stok_takip' => true,
            'stok_miktari' => '10.0000',
        ]);

        $satis = $this->satisOlustur($firma, $user, $stok, 1);

        $sayfa = app(BarkodluSatisIadeGecmisiSayfasi::class);
        $sayfa->mount();
        $sayfa->hizliIade['tek_kalem_otomatik_kaydet'] = true;
        $sayfa->hizliIade['satis_no'] = (string) $satis->satis_no;
        $sayfa->hizliIadeSatisiniYukle();

        $this->assertDatabaseHas('muhasebe_barkodlu_satis_iadeler', [
            'firma_id' => (int) $firma->id,
            'satis_id' => (int) $satis->id,
        ]);
    }

    public function test_otomatik_iade_geri_al_akisi_iadeyi_siler_ve_stogu_tersler(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('UNDO-IAD');
        $stok = $this->stokOlustur($firma, [
            'stok_takip' => true,
            'stok_miktari' => '10.0000',
        ]);

        $satis = $this->satisOlustur($firma, $user, $stok, 1);
        $stok->refresh();
        $this->assertSame('9.0000', number_format((float) $stok->stok_miktari, 4, '.', ''));

        $sayfa = app(BarkodluSatisIadeGecmisiSayfasi::class);
        $sayfa->mount();
        $sayfa->hizliIade['tek_kalem_otomatik_kaydet'] = true;
        $sayfa->hizliIade['satis_no'] = (string) $satis->satis_no;
        $sayfa->hizliIadeSatisiniYukle();

        $iadeId = (int) ($sayfa->sonOtomatikIadeId ?? 0);
        $this->assertGreaterThan(0, $iadeId);
        $this->assertDatabaseHas('muhasebe_barkodlu_satis_iadeler', [
            'id' => $iadeId,
            'firma_id' => (int) $firma->id,
        ]);

        $sayfa->sonOtomatikIadeyiGeriAl();

        $this->assertDatabaseMissing('muhasebe_barkodlu_satis_iadeler', [
            'id' => $iadeId,
        ]);

        $stok->refresh();
        $this->assertSame('9.0000', number_format((float) $stok->stok_miktari, 4, '.', ''));
    }

    public function test_hizli_iade_geri_alma_suresi_firma_ayarindan_okunur(): void
    {
        [, $firma] = $this->superAdminVeFirmaSession('IAD-SURE');

        app(FirmaAyarDeposu::class)->yaz((int) $firma->id, 'barkodlu_iade_geri_alma_suresi_saniye', 12);

        $sayfa = app(BarkodluSatisIadeGecmisiSayfasi::class);
        $sayfa->mount();

        $this->assertSame(12, $sayfa->otomatikIadeGeriAlmaSuresiSaniye);
    }

    private function superAdminVeFirmaSession(string $kod): array
    {
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

        KasaHesabi::query()->create([
            'firma_id' => (int) $firma->id,
            'kod' => 'KASA-'.$kod.'-'.uniqid(),
            'ad' => 'Test Kasası',
            'para_birimi' => 'TRY',
            'durum' => HesapDurumu::Aktif->value,
        ]);

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

    private function satisOlustur(Firma $firma, User $user, StokKarti $stok, float $miktar): BarkodluSatis
    {
        /** @var BarkodluSatis $satis */
        $satis = app(BarkodluSatisServisi::class)->satisTamamla((int) $firma->id, (int) $user->id, [
            'satis_tarihi' => now()->toDateTimeString(),
            'odeme_tipi' => 'nakit',
            'para_birimi' => 'TRY',
            'kalemler' => [[
                'stok_id' => (int) $stok->id,
                'miktar' => $miktar,
                'birim_fiyat' => 100,
                'kdv_orani' => 20,
            ]],
        ]);

        return $satis->fresh(['kalemler']) ?? $satis;
    }

    private function iadeFisiImzasi(int $iadeId, string $dogrulamaKodu): string
    {
        $anahtar = (string) config('app.key');
        if (str_starts_with($anahtar, 'base64:')) {
            $cozulmus = base64_decode(substr($anahtar, 7), true);
            if ($cozulmus !== false) {
                $anahtar = $cozulmus;
            }
        }

        return hash_hmac('sha256', 'iade_fis|'.$iadeId.'|'.$dogrulamaKodu, $anahtar);
    }
}
