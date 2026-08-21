<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\StokKarti;
use App\Models\SistemOlayi;
use App\Models\User;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Muhasebe\Servisler\BarkodluSatisServisi;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

class BarkodluSatisIzlemeHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_satis_olusturmada_olay_ve_metrik_kaydedilir(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('IZL-SAT');
        $stok = $this->stokOlustur($firma, [
            'stok_takip' => true,
            'stok_miktari' => '10.0000',
        ]);

        Cache::forget('muhasebe:metrics:barkodlu_satis:olusturuldu');
        app(BarkodluSatisServisi::class)->satisTamamla((int) $firma->id, (int) $user->id, [
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

        $this->assertDatabaseHas('sistem_olaylari', [
            'tip' => 'barkodlu_satis.satis_olusturuldu',
            'seviye' => 'info',
            'firma_id' => (int) $firma->id,
        ]);
        $this->assertSame(1, (int) Cache::get('muhasebe:metrics:barkodlu_satis:olusturuldu', 0));
    }

    public function test_iptal_ve_iade_aksiyonlari_olay_ve_metrik_uretir(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('IZL-AKS');
        $stok = $this->stokOlustur($firma, [
            'stok_takip' => true,
            'stok_miktari' => '20.0000',
        ]);

        $satis = app(BarkodluSatisServisi::class)->satisTamamla((int) $firma->id, (int) $user->id, [
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
        $kalemId = (int) $satis->kalemler()->value('id');

        Cache::forget('muhasebe:metrics:barkodlu_satis:iade');
        Cache::forget('muhasebe:metrics:barkodlu_satis:iptal');

        app(BarkodluSatisServisi::class)->satisKalemiIadeEt((int) $firma->id, (int) $satis->id, $kalemId, 1, (int) $user->id, 'kismi');
        app(BarkodluSatisServisi::class)->satisIptalEt((int) $firma->id, (int) $satis->id, (int) $user->id, 'iptal');

        $this->assertDatabaseHas('sistem_olaylari', [
            'tip' => 'barkodlu_satis.satis_iade_olusturuldu',
            'seviye' => 'info',
            'firma_id' => (int) $firma->id,
        ]);
        $this->assertDatabaseHas('sistem_olaylari', [
            'tip' => 'barkodlu_satis.satis_iptal_edildi',
            'seviye' => 'warning',
            'firma_id' => (int) $firma->id,
        ]);
        $this->assertSame(1, (int) Cache::get('muhasebe:metrics:barkodlu_satis:iade', 0));
        $this->assertSame(1, (int) Cache::get('muhasebe:metrics:barkodlu_satis:iptal', 0));
    }

    public function test_hata_durumunda_hata_olayi_ve_hata_metrigi_kaydedilir(): void
    {
        [$user, $firmaA] = $this->superAdminVeFirmaSession('IZL-HTA-A');
        [, $firmaB] = $this->superAdminVeFirmaSession('IZL-HTA-B');

        $stokB = $this->stokOlustur($firmaB, [
            'stok_takip' => true,
            'stok_miktari' => '5.0000',
        ]);

        Cache::forget('muhasebe:metrics:barkodlu_satis:hata');

        try {
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
            $this->fail('InvalidArgumentException bekleniyordu.');
        } catch (InvalidArgumentException) {
            // beklenen
        }

        $hataOlayi = SistemOlayi::query()->withoutGlobalScopes()
            ->where('tip', 'barkodlu_satis.satis_hatasi')
            ->latest('id')
            ->first();

        $this->assertNotNull($hataOlayi);
        $this->assertSame('error', (string) $hataOlayi->seviye);
        $this->assertSame((int) $firmaA->id, (int) $hataOlayi->firma_id);
        $this->assertSame(1, (int) Cache::get('muhasebe:metrics:barkodlu_satis:hata', 0));
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
}
