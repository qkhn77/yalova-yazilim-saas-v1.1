<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaFinansKapama;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\User;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Enumlar\FinansHareketDurumu;
use App\Muhasebe\Enumlar\FinansHareketTuru;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Servisler\FaturaFinansKapamaServisi;
use App\Muhasebe\Servisler\MuhasebeSistemDogrulamaServisi;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinansHareketiTenantScopeTest extends TestCase
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

    public function test_tenant_global_scope_baska_firma_finans_bulunamaz(): void
    {
        $fa = $this->firmaOlustur('FHA');
        $fb = $this->firmaOlustur('FHB');

        $cariB = Cari::withoutGlobalScopes()->create([
            'firma_id' => $fb->id,
            'kod' => 'C-B-'.uniqid(),
            'ad' => 'Cari B',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        $finansB = FinansHareketi::withoutGlobalScopes()->create([
            'firma_id' => $fb->id,
            'tur' => FinansHareketTuru::Tahsilat->value,
            'tarih' => now(),
            'vade_tarihi' => null,
            'tutar' => '100.00',
            'para_birimi' => 'TRY',
            'cari_id' => $cariB->id,
            'aciklama' => null,
            'referans_turu' => null,
            'referans_id' => null,
            'durum' => FinansHareketDurumu::Aktif->value,
            'iptal_edilen_hareket_id' => null,
        ]);

        $kullanici = User::query()->create([
            'name' => 'K',
            'email' => 'k-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => false,
        ]);

        $this->actingAs($kullanici);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $fa->id]);

        $this->assertNull(FinansHareketi::query()->find($finansB->id));
    }

    public function test_super_admin_scope_tum_firmalarin_finansini_gorebilir(): void
    {
        $fa = $this->firmaOlustur('FSA');
        $fb = $this->firmaOlustur('FSB');

        $cariB = Cari::withoutGlobalScopes()->create([
            'firma_id' => $fb->id,
            'kod' => 'C-SA-'.uniqid(),
            'ad' => 'Cari',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        $finansB = FinansHareketi::withoutGlobalScopes()->create([
            'firma_id' => $fb->id,
            'tur' => FinansHareketTuru::Tahsilat->value,
            'tarih' => now(),
            'vade_tarihi' => null,
            'tutar' => '50.00',
            'para_birimi' => 'TRY',
            'cari_id' => $cariB->id,
            'aciklama' => null,
            'referans_turu' => null,
            'referans_id' => null,
            'durum' => FinansHareketDurumu::Aktif->value,
            'iptal_edilen_hareket_id' => null,
        ]);

        $sa = User::query()->create([
            'name' => 'SA',
            'email' => 'sa-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => true,
        ]);

        $this->actingAs($sa);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $fa->id]);

        $this->assertNotNull(FinansHareketi::query()->find($finansB->id));
    }

    public function test_cari_kullanilabilir_avans_servisi_tenant_firma_izolasyonu(): void
    {
        $fa = $this->firmaOlustur('FCA');
        $fb = $this->firmaOlustur('FCB');

        $cariFa = Cari::withoutGlobalScopes()->create([
            'firma_id' => $fa->id,
            'kod' => 'CFA-'.uniqid(),
            'ad' => 'A',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);
        $cariFb = Cari::withoutGlobalScopes()->create([
            'firma_id' => $fb->id,
            'kod' => 'CFB-'.uniqid(),
            'ad' => 'B',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        FinansHareketi::withoutGlobalScopes()->create([
            'firma_id' => $fa->id,
            'tur' => FinansHareketTuru::Tahsilat->value,
            'tarih' => now(),
            'vade_tarihi' => null,
            'tutar' => '80.00',
            'para_birimi' => 'TRY',
            'cari_id' => $cariFa->id,
            'aciklama' => null,
            'referans_turu' => null,
            'referans_id' => null,
            'durum' => FinansHareketDurumu::Aktif->value,
            'iptal_edilen_hareket_id' => null,
        ]);
        FinansHareketi::withoutGlobalScopes()->create([
            'firma_id' => $fb->id,
            'tur' => FinansHareketTuru::Tahsilat->value,
            'tarih' => now(),
            'vade_tarihi' => null,
            'tutar' => '90.00',
            'para_birimi' => 'TRY',
            'cari_id' => $cariFb->id,
            'aciklama' => null,
            'referans_turu' => null,
            'referans_id' => null,
            'durum' => FinansHareketDurumu::Aktif->value,
            'iptal_edilen_hareket_id' => null,
        ]);

        $kullanici = User::query()->create([
            'name' => 'T',
            'email' => 't-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => false,
        ]);
        $this->actingAs($kullanici);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $fa->id]);

        $servis = app(FaturaFinansKapamaServisi::class);
        $ozetA = $servis->cariKullanilabilirAvansOzeti((int) $fa->id, (int) $cariFa->id);
        $this->assertSame(1, $ozetA['satir_sayisi']);
        $this->assertSame('80.00000000', $ozetA['toplam_avans']);

        $this->expectException(IsKuraliIstisnasi::class);
        $servis->cariKullanilabilirAvansOzeti((int) $fb->id, (int) $cariFb->id);
    }

    public function test_sistem_dogrulama_kapama_satirinda_finans_yuklenir_auth_olmadan(): void
    {
        $firma = $this->firmaOlustur('FSK');

        $cari = Cari::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kod' => 'C-'.uniqid(),
            'ad' => 'C',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        $fatura = Fatura::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
            'tur' => FaturaTuru::Giden->value,
            'durum' => FaturaDurumu::Onayli->value,
            'tarih' => now(),
            'ara_toplam' => '100',
            'kdv_toplam' => '20',
            'genel_toplam' => '120',
            'genel_indirim_tutari' => '0',
            'toplam_indirim' => '0',
            'odenecek_tutar' => '120',
            'odendi_tutari' => '0',
            'acik_tutar' => '120',
            'para_birimi' => 'TRY',
            'doviz_kuru' => '1',
        ]);

        $finans = FinansHareketi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'tur' => FinansHareketTuru::Tahsilat->value,
            'tarih' => now(),
            'vade_tarihi' => null,
            'tutar' => '40.00',
            'para_birimi' => 'TRY',
            'cari_id' => $cari->id,
            'aciklama' => null,
            'referans_turu' => null,
            'referans_id' => null,
            'durum' => FinansHareketDurumu::Aktif->value,
            'iptal_edilen_hareket_id' => null,
        ]);

        FaturaFinansKapama::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'fatura_id' => $fatura->id,
            'finans_hareket_id' => $finans->id,
            'uygulanan_tutar' => '40.00',
            'para_birimi' => 'TRY',
        ]);

        $hatalar = app(MuhasebeSistemDogrulamaServisi::class)->sistemTutarlilikKontrolu((int) $firma->id, false);
        $kodlar = array_column($hatalar, 'kod');
        $this->assertNotContains('kapama_finans_yok', $kodlar);
    }
}
