<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\StokHareketi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokDepoBakiyesi;
use App\Models\User;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokBelgeTuru;
use App\Muhasebe\Enumlar\StokHareketDurumu;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Servisler\StokHareketServisi;
use App\Services\FirmaAyarDeposu;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StokConcurrencyVeBackfillTest extends TestCase
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
            'name' => 'SA',
            'email' => 'sa-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => true,
        ]);

        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);
    }

    private function stokOlustur(Firma $firma, string $miktar = '10'): StokKarti
    {
        return StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'STK-'.uniqid(),
            'ad' => 'Stok',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'stok_takip' => true,
            'stok_miktari' => $miktar,
            'minimum_stok' => '1',
            'para_birimi' => 'TRY',
        ]);
    }

    public function test_ardisik_stok_azaltmada_tutarli_onceki_sonraki_ve_negatif_koruma(): void
    {
        $firma = $this->firmaOlustur('SC1');
        $this->superAdminVeSession($firma);
        $stok = $this->stokOlustur($firma, '10');
        $servis = app(StokHareketServisi::class);

        $h1 = $servis->kayitOlustur($firma->id, [
            'stok_id' => $stok->id,
            'islem_turu' => StokHareketIslemTuru::Satis,
            'miktar' => '6',
            'birim_fiyat' => '10',
            'belge_turu' => StokBelgeTuru::Fatura,
            'belge_id' => 1001,
            'tarih' => now(),
        ]);

        $this->assertSame('10.0000', number_format((float) $h1->onceki_miktar, 4, '.', ''));
        $this->assertSame('4.0000', number_format((float) $h1->sonraki_miktar, 4, '.', ''));

        try {
            $servis->kayitOlustur($firma->id, [
                'stok_id' => $stok->id,
                'islem_turu' => StokHareketIslemTuru::Satis,
                'miktar' => '5',
                'birim_fiyat' => '10',
                'belge_turu' => StokBelgeTuru::Fatura,
                'belge_id' => 1002,
                'tarih' => now(),
            ]);
            $this->fail('Negatif stok koruması devreye girmeliydi.');
        } catch (IsKuraliIstisnasi $e) {
            $this->assertStringContainsString('negatife düşürür', $e->getMessage());
        }

        $this->assertSame('4.0000', number_format((float) $stok->fresh()->stok_miktari, 4, '.', ''));
    }

    public function test_depo_sayimi_farki_depo_ve_genel_stogu_gunceller(): void
    {
        $firma = $this->firmaOlustur('SC-SAYIM');
        $this->superAdminVeSession($firma);
        $stok = $this->stokOlustur($firma, '4');
        $depo = Depo::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'MERKEZ-'.uniqid(),
            'ad' => 'Merkez Depo',
            'varsayilan_mi' => true,
            'aktif_mi' => true,
        ]);
        StokDepoBakiyesi::query()->create([
            'firma_id' => $firma->id,
            'depo_id' => $depo->id,
            'stok_id' => $stok->id,
            'miktar' => '4',
            'rezerve_miktar' => '0',
        ]);

        $hareket = app(StokHareketServisi::class)->depoSayiminiUygula(
            $firma->id,
            $stok->id,
            $depo->id,
            '7',
            7001,
            'Raf sayımı',
        );

        $this->assertSame(StokBelgeTuru::Sayim, $hareket->belge_turu);
        $this->assertSame('4.0000', number_format((float) $hareket->onceki_miktar, 4, '.', ''));
        $this->assertSame('7.0000', number_format((float) $hareket->sonraki_miktar, 4, '.', ''));
        $this->assertSame('7.0000', number_format((float) $stok->fresh()->stok_miktari, 4, '.', ''));
        $this->assertSame('7.0000', number_format((float) StokDepoBakiyesi::query()
            ->where('depo_id', $depo->id)
            ->where('stok_id', $stok->id)
            ->value('miktar'), 4, '.', ''));

        $this->expectException(IsKuraliIstisnasi::class);
        app(StokHareketServisi::class)->depoSayiminiUygula($firma->id, $stok->id, $depo->id, '7', 7002);
    }

    public function test_ters_kayit_stogu_tutarlı_toparlar(): void
    {
        $firma = $this->firmaOlustur('SC2');
        $this->superAdminVeSession($firma);
        $stok = $this->stokOlustur($firma, '10');
        $servis = app(StokHareketServisi::class);

        $hareket = $servis->kayitOlustur($firma->id, [
            'stok_id' => $stok->id,
            'islem_turu' => StokHareketIslemTuru::Satis,
            'miktar' => '3',
            'birim_fiyat' => '20',
            'belge_turu' => StokBelgeTuru::Fatura,
            'belge_id' => 2001,
            'tarih' => now(),
        ]);
        $this->assertSame('7.0000', number_format((float) $stok->fresh()->stok_miktari, 4, '.', ''));

        $ters = $servis->tersKayitOlustur($hareket->fresh(), 'test ters');
        $this->assertSame('7.0000', number_format((float) $ters->onceki_miktar, 4, '.', ''));
        $this->assertSame('10.0000', number_format((float) $ters->sonraki_miktar, 4, '.', ''));
        $this->assertSame('10.0000', number_format((float) $stok->fresh()->stok_miktari, 4, '.', ''));
    }

    public function test_ayni_hareket_ikinci_kez_terslenemez(): void
    {
        $firma = $this->firmaOlustur('SC2B');
        $this->superAdminVeSession($firma);
        $stok = $this->stokOlustur($firma, '10');
        $servis = app(StokHareketServisi::class);

        $hareket = $servis->kayitOlustur($firma->id, [
            'stok_id' => $stok->id,
            'islem_turu' => StokHareketIslemTuru::Satis,
            'miktar' => '2',
            'birim_fiyat' => '20',
            'belge_turu' => StokBelgeTuru::Fatura,
            'belge_id' => 2201,
            'tarih' => now(),
        ]);

        $servis->tersKayitOlustur($hareket->fresh(), 'ilk ters');

        $this->expectException(IsKuraliIstisnasi::class);
        $this->expectExceptionMessage('Yalnızca aktif stok hareketi terslenebilir.');
        $servis->tersKayitOlustur($hareket->fresh(), 'ikinci ters');
    }

    public function test_stok_yeniden_hesapla_dry_run_ve_yazma_modu(): void
    {
        $firma = $this->firmaOlustur('SC3');
        $this->superAdminVeSession($firma);
        $stok = $this->stokOlustur($firma, '0');
        StokHareketi::query()->create([
            'firma_id' => $firma->id,
            'stok_id' => $stok->id,
            'islem_turu' => StokHareketIslemTuru::Alis,
            'miktar' => '1',
            'onceki_miktar' => '0',
            'sonraki_miktar' => '1',
            'birim_fiyat' => '10',
            'birim_maliyet' => '10',
            'toplam' => '10',
            'toplam_maliyet' => '10',
            'belge_turu' => StokBelgeTuru::Fatura,
            'referans_tipi' => StokBelgeTuru::Fatura->value,
            'belge_id' => 3001,
            'referans_id' => 3001,
            'tarih' => now(),
            'islem_tarihi' => now(),
            'durum' => StokHareketDurumu::Aktif,
        ]);
        StokHareketi::query()->create([
            'firma_id' => $firma->id,
            'stok_id' => $stok->id,
            'islem_turu' => StokHareketIslemTuru::Satis,
            'miktar' => '2',
            'onceki_miktar' => '1',
            'sonraki_miktar' => '-1',
            'birim_fiyat' => '10',
            'birim_maliyet' => '10',
            'toplam' => '20',
            'toplam_maliyet' => '20',
            'belge_turu' => StokBelgeTuru::Fatura,
            'referans_tipi' => StokBelgeTuru::Fatura->value,
            'belge_id' => 3002,
            'referans_id' => 3002,
            'tarih' => now(),
            'islem_tarihi' => now(),
            'durum' => StokHareketDurumu::Aktif,
        ]);

        StokKarti::query()->whereKey($stok->id)->update(['stok_miktari' => '999']);
        $this->artisan('stok:yeniden-hesapla', ['--dry-run' => true, '--stok_id' => $stok->id])
            ->assertSuccessful();
        $this->assertSame('999.0000', number_format((float) $stok->fresh()->stok_miktari, 4, '.', ''));

        $this->artisan('stok:yeniden-hesapla', ['--stok_id' => $stok->id])->assertSuccessful();
        $this->assertSame('999.0000', number_format((float) $stok->fresh()->stok_miktari, 4, '.', ''));

        $this->artisan('stok:yeniden-hesapla', ['--stok_id' => $stok->id, '--riskli-duzelt' => true])->assertSuccessful();
        $this->assertSame('-1.0000', number_format((float) $stok->fresh()->stok_miktari, 4, '.', ''));
    }

    public function test_stok_yeniden_hesapla_legacy_acilis_stok_kaydini_uyari_olarak_isaretler(): void
    {
        $firma = $this->firmaOlustur('SC4');
        $this->superAdminVeSession($firma);
        $stok = $this->stokOlustur($firma, '15');

        $this->artisan('stok:yeniden-hesapla', ['--dry-run' => true, '--stok_id' => $stok->id])
            ->assertSuccessful()
            ->expectsOutputToContain('INCELENMELI_LEGACY_ACILIS_STOK');

        $this->artisan('stok:yeniden-hesapla', ['--stok_id' => $stok->id])->assertSuccessful();
        $this->assertSame('15.0000', number_format((float) $stok->fresh()->stok_miktari, 4, '.', ''));
    }

    public function test_ardisik_islemlerde_miktar_ve_maliyet_tutarli_kalir(): void
    {
        $firma = $this->firmaOlustur('SC5');
        $this->superAdminVeSession($firma);
        $stok = $this->stokOlustur($firma, '0');
        $servis = app(StokHareketServisi::class);

        $servis->kayitOlustur($firma->id, [
            'stok_id' => $stok->id,
            'islem_turu' => StokHareketIslemTuru::Alis,
            'miktar' => '10',
            'birim_fiyat' => '20',
            'belge_turu' => StokBelgeTuru::Fatura,
            'belge_id' => 5001,
            'tarih' => now(),
        ]);
        $servis->kayitOlustur($firma->id, [
            'stok_id' => $stok->id,
            'islem_turu' => StokHareketIslemTuru::Satis,
            'miktar' => '3',
            'birim_fiyat' => '30',
            'belge_turu' => StokBelgeTuru::Fatura,
            'belge_id' => 5002,
            'tarih' => now(),
        ]);
        $servis->kayitOlustur($firma->id, [
            'stok_id' => $stok->id,
            'islem_turu' => StokHareketIslemTuru::Satis,
            'miktar' => '2',
            'birim_fiyat' => '30',
            'belge_turu' => StokBelgeTuru::Fatura,
            'belge_id' => 5003,
            'tarih' => now(),
        ]);

        $guncel = $stok->fresh();
        $this->assertSame('5.0000', number_format((float) $guncel->stok_miktari, 4, '.', ''));
        $this->assertSame('20.00', number_format((float) $guncel->guncel_birim_maliyet, 2, '.', ''));
        $this->assertSame('100.00', number_format((float) $guncel->stok_degeri, 2, '.', ''));
    }

    public function test_negative_flag_izin_degildir_ve_firma_ayari_tenant_bazinda_calisir(): void
    {
        $firma = $this->firmaOlustur('SC-NEG');
        $this->superAdminVeSession($firma);
        $stok = $this->stokOlustur($firma, '0');
        $stok->update(['negative_flag' => true]);
        $digerFirma = $this->firmaOlustur('SC-NEG-DIGER');
        app(FirmaAyarDeposu::class)->yaz($digerFirma->id, 'negatif_stok_izinli', true);
        $servis = app(StokHareketServisi::class);
        $payload = [
            'stok_id' => $stok->id, 'islem_turu' => StokHareketIslemTuru::Satis,
            'miktar' => '1', 'birim_fiyat' => '10', 'belge_turu' => StokBelgeTuru::Fatura,
            'belge_id' => 6001, 'tarih' => now(),
        ];

        try {
            $servis->kayitOlustur($firma->id, $payload);
            $this->fail('negative_flag tek başına negatif stok izni vermemeliydi.');
        } catch (IsKuraliIstisnasi) {
            $this->assertSame('0.0000', number_format((float) $stok->fresh()->stok_miktari, 4, '.', ''));
        }

        app(FirmaAyarDeposu::class)->yaz($firma->id, 'negatif_stok_izinli', true);
        $servis->kayitOlustur($firma->id, array_merge($payload, ['belge_id' => 6002]));
        $this->assertTrue((bool) $stok->fresh()->negative_flag);

        $servis->kayitOlustur($firma->id, [
            'stok_id' => $stok->id, 'islem_turu' => StokHareketIslemTuru::Alis,
            'miktar' => '1', 'birim_fiyat' => '10', 'belge_turu' => StokBelgeTuru::Fatura,
            'belge_id' => 6003, 'tarih' => now(),
        ]);
        $this->assertFalse((bool) $stok->fresh()->negative_flag);
    }

    public function test_ters_kayit_miktar_ve_maliyet_ozetlerini_birlikte_toparlar(): void
    {
        $firma = $this->firmaOlustur('SC-TERS-M');
        $this->superAdminVeSession($firma);
        $stok = $this->stokOlustur($firma, '0');
        $servis = app(StokHareketServisi::class);
        $giris = $servis->kayitOlustur($firma->id, [
            'stok_id' => $stok->id, 'islem_turu' => StokHareketIslemTuru::Alis,
            'miktar' => '10', 'birim_fiyat' => '20', 'birim_maliyet' => '20',
            'belge_turu' => StokBelgeTuru::Fatura, 'belge_id' => 6101, 'tarih' => now(),
        ]);
        $cikis = $servis->kayitOlustur($firma->id, [
            'stok_id' => $stok->id, 'islem_turu' => StokHareketIslemTuru::Satis,
            'miktar' => '3', 'birim_fiyat' => '30', 'belge_turu' => StokBelgeTuru::Fatura,
            'belge_id' => 6102, 'tarih' => now(),
        ]);

        $servis->tersKayitOlustur($cikis);
        $guncel = $stok->fresh();
        $this->assertSame('10.0000', number_format((float) $guncel->stok_miktari, 4, '.', ''));
        $this->assertSame('200.00', number_format((float) $guncel->stok_degeri, 2, '.', ''));
        $this->assertSame('20.00', number_format((float) $guncel->guncel_birim_maliyet, 2, '.', ''));

        $servis->tersKayitOlustur($giris);
        $guncel = $stok->fresh();
        $this->assertSame('0.0000', number_format((float) $guncel->stok_miktari, 4, '.', ''));
        $this->assertSame('0.00', number_format((float) $guncel->stok_degeri, 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $guncel->guncel_birim_maliyet, 2, '.', ''));
    }
}
