<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\BarkodluSatis;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\StokKarti;
use App\Models\User;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Muhasebe\Servisler\BarkodluSatisServisi;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BarkodluSatisMutabakatKomutuTest extends TestCase
{
    use RefreshDatabase;

    public function test_mutabakat_komutu_sorunsuz_kayitta_basari_doner(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('MUT-OK');
        $stok = $this->stokOlustur($firma);
        $this->kasaOlustur($firma);

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

        $this->artisan('barkodlu-satis:mutabakat-dogrula --firma_id='.$firma->id.' --days=30 --critical-only')
            ->assertExitCode(0);
    }

    public function test_finans_hareketi_yoksa_barkodlu_satis_geri_alinir(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('MUT-ERR');
        $stok = $this->stokOlustur($firma);
        // Kasa tanimlamiyoruz: finans hareketi olmadan satis tamamlanamaz.

        try {
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
            $this->fail('Kasa yokken satis tamamlanmamali.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('finans hareketi oluşturulamadı', $e->getMessage());
        }

        $this->assertDatabaseCount('muhasebe_barkodlu_satislar', 0);
    }

    public function test_finans_hareketi_yoksa_mutabakat_sorunu_olusturulmaz(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('MUT-CACHE');
        $stok = $this->stokOlustur($firma);
        // Kasa yok: satış transaction içinde geri alınır.
        try {
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
            $this->fail('Kasa yokken satis tamamlanmamali.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('finans hareketi oluşturulamadı', $e->getMessage());
        }

        $this->assertDatabaseCount('muhasebe_barkodlu_satislar', 0);
        $this->assertNull(Cache::get('barkodlu_satis:mutabakat:sonuc:firma:'.$firma->id));
    }

    public function test_mutabakat_komutu_iade_finans_eksigini_yakalar(): void
    {
        [$user, $firma] = $this->superAdminVeFirmaSession('MUT-IADE');
        $stok = $this->stokOlustur($firma);
        $this->kasaOlustur($firma);

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

        /** @var BarkodluSatis $satis */
        $satis = BarkodluSatis::query()
            ->where('firma_id', (int) $firma->id)
            ->latest('id')
            ->with('kalemler')
            ->firstOrFail();

        $kalemId = (int) $satis->kalemler->firstOrFail()->id;
        $iade = app(BarkodluSatisServisi::class)->satisKalemiIadeEt(
            (int) $firma->id,
            (int) $satis->id,
            $kalemId,
            1,
            (int) $user->id,
            'mutabakat test iade'
        );

        FinansHareketi::query()
            ->withoutGlobalScopes()
            ->where('firma_id', (int) $firma->id)
            ->where('referans_turu', 'barkodlu_satis_iade')
            ->where('referans_id', (int) $iade->id)
            ->update(['durum' => 'iptal']);

        $this->artisan('barkodlu-satis:mutabakat-dogrula --firma_id='.$firma->id.' --days=30 --critical-only')
            ->expectsOutputToContain('iade_finans_eksik')
            ->assertExitCode(1);
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
}
