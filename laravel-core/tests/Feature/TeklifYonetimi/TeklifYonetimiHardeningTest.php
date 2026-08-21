<?php

namespace Tests\Feature\TeklifYonetimi;

use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\Teklif;
use App\Models\Muhasebe\TeklifKalemi;
use App\Models\TeklifYonetimi\TeklifBaskiSablonu;
use App\Models\User;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Policies\TeklifBaskiSablonuPolicy;
use App\Policies\TeklifPolicy;
use App\Services\TenantContextService;
use App\TeklifYonetimi\Servisler\TeklifIsAkisiServisi;
use App\TeklifYonetimi\Servisler\TeklifNumaraServisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeklifYonetimiHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_global_scope_baska_firma_teklifini_gizler(): void
    {
        $firmaA = $this->firmaOlustur('TKLA');
        $firmaB = $this->firmaOlustur('TKLB');

        $teklifB = Teklif::query()->create([
            'firma_id' => $firmaB->id,
            'teklif_no' => 'TKL-2026-0001',
            'durum' => 'taslak',
            'baslik' => 'B firmasi teklifi',
            'tarih' => now(),
            'para_birimi' => 'TRY',
        ]);

        $this->actingAs(User::factory()->create(['super_admin_mi' => false]));
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firmaA->id]);

        $this->assertNull(Teklif::query()->find($teklifB->id));
    }

    public function test_teklif_kalemi_firma_id_degerini_bagli_tekliften_alir(): void
    {
        $firmaA = $this->firmaOlustur('TKLC');
        $firmaB = $this->firmaOlustur('TKLD');

        $teklif = Teklif::query()->create([
            'firma_id' => $firmaA->id,
            'teklif_no' => 'TKL-2026-0002',
            'durum' => 'taslak',
            'baslik' => 'Firma A teklifi',
            'tarih' => now(),
            'para_birimi' => 'TRY',
        ]);

        $kalem = TeklifKalemi::query()->create([
            'firma_id' => $firmaB->id,
            'teklif_id' => $teklif->id,
            'aciklama' => 'Test kalemi',
            'birim' => 'AD',
            'miktar' => 1,
            'birim_fiyat' => 100,
            'net_tutar' => 100,
            'kdv_tutari' => 20,
            'toplam' => 120,
            'para_birimi' => 'TRY',
        ]);

        $kayit = TeklifKalemi::tenantScopeOlmadan(fn () => TeklifKalemi::query()->findOrFail($kalem->id));

        $this->assertSame((int) $firmaA->id, (int) $kayit->firma_id);
    }

    public function test_tenant_global_scope_baska_firma_teklif_sablonunu_gizler(): void
    {
        $firmaA = $this->firmaOlustur('TKLE');
        $firmaB = $this->firmaOlustur('TKLF');

        $sablonB = TeklifBaskiSablonu::query()->create([
            'firma_id' => $firmaB->id,
            'ad' => 'B firmasi sablonu',
            'kod' => 'b-firmasi-sablonu',
            'sayfa_tipi' => 'a4',
            'sablon_html' => '<p>{{TEKLIF_NO}}</p>',
            'aktif' => true,
        ]);

        $this->actingAs(User::factory()->create(['super_admin_mi' => false]));
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firmaA->id]);

        $this->assertNull(TeklifBaskiSablonu::query()->find($sablonB->id));
    }

    public function test_faturaya_donusmus_teklif_silinemez(): void
    {
        $firma = $this->firmaOlustur('TKLG');
        $kullanici = User::factory()->create(['super_admin_mi' => true]);
        $teklif = new Teklif([
            'firma_id' => $firma->id,
            'faturaya_donustu_fatura_id' => 123,
        ]);

        $this->assertFalse(app(TeklifPolicy::class)->delete($kullanici, $teklif));
        $this->assertTrue(app(TeklifPolicy::class)->deleteAny($kullanici));
    }

    public function test_varsayilan_teklif_sablonu_silinemez(): void
    {
        $firma = $this->firmaOlustur('TKLH');
        $kullanici = User::factory()->create(['super_admin_mi' => true]);
        $sablon = new TeklifBaskiSablonu([
            'firma_id' => $firma->id,
            'varsayilan_mi' => true,
        ]);

        $this->assertFalse(app(TeklifBaskiSablonuPolicy::class)->delete($kullanici, $sablon));
        $this->assertTrue(app(TeklifBaskiSablonuPolicy::class)->deleteAny($kullanici));
    }

    public function test_teklif_numarasi_firma_ve_yil_bazinda_kilitli_sayactan_uretilir(): void
    {
        $firmaA = $this->firmaOlustur('TKLI');
        $firmaB = $this->firmaOlustur('TKLJ');

        Teklif::query()->create([
            'firma_id' => $firmaA->id,
            'teklif_no' => 'TKL-2026-0007',
            'durum' => 'taslak',
            'baslik' => 'Eski teklif',
            'tarih' => '2026-04-10',
            'para_birimi' => 'TRY',
        ]);

        $servis = app(TeklifNumaraServisi::class);

        $this->assertSame('TKL-2026-0008', $servis->benzersizUret((int) $firmaA->id, '2026-06-06'));
        $this->assertSame('TKL-2026-0009', $servis->benzersizUret((int) $firmaA->id, '2026-06-06'));
        $this->assertSame('TKL-2026-0001', $servis->benzersizUret((int) $firmaB->id, '2026-06-06'));
    }

    public function test_teklif_durum_aksiyonlari_ilgili_tarihleri_isler(): void
    {
        $firma = $this->firmaOlustur('TKLK');
        $teklif = Teklif::query()->create([
            'firma_id' => $firma->id,
            'teklif_no' => 'TKL-2026-0010',
            'durum' => 'taslak',
            'baslik' => 'Durum testi',
            'tarih' => now(),
            'para_birimi' => 'TRY',
        ]);

        $servis = app(TeklifIsAkisiServisi::class);

        $gonderildi = $servis->durumDegistir($teklif, 'gonderildi');
        $this->assertSame('gonderildi', $gonderildi->durum);
        $this->assertNotNull($gonderildi->gonderildi_at);
        $this->assertNull($gonderildi->yanitlandi_at);

        $onaylandi = $servis->durumDegistir($gonderildi, 'onaylandi');
        $this->assertSame('onaylandi', $onaylandi->durum);
        $this->assertNotNull($onaylandi->gonderildi_at);
        $this->assertNotNull($onaylandi->yanitlandi_at);
    }

    public function test_onayli_teklif_bekleyen_faturaya_mevcut_tutarlarla_donusturulur(): void
    {
        $firma = $this->firmaOlustur('TKLL');
        $cari = Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'CR-TKLL',
            'ad' => 'Test Cari',
            'tur' => 'musteri',
            'durum' => 'aktif',
            'para_birimi' => 'TRY',
        ]);

        $teklif = Teklif::query()->create([
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
            'teklif_no' => 'TKL-2026-0011',
            'durum' => 'onaylandi',
            'baslik' => 'Fatura dönüşüm testi',
            'tarih' => now(),
            'yanitlandi_at' => now(),
            'para_birimi' => 'TRY',
            'ara_toplam' => 180,
            'toplam_indirim' => 20,
            'kdv_toplam' => 36,
            'genel_toplam' => 216,
        ]);

        TeklifKalemi::query()->create([
            'firma_id' => $firma->id,
            'teklif_id' => $teklif->id,
            'kalem_tipi' => 'stok_kalemi',
            'aciklama' => 'Kamera montaj',
            'birim' => 'AD',
            'miktar' => 2,
            'birim_fiyat' => 100,
            'indirim_orani' => 10,
            'kdv_orani' => 20,
            'net_tutar' => 180,
            'kdv_tutari' => 36,
            'toplam' => 216,
            'para_birimi' => 'TRY',
        ]);

        $servis = app(TeklifIsAkisiServisi::class);
        $fatura = $servis->bekleyenFaturaOlustur($teklif);

        $this->assertSame(FaturaTuru::BekleyenFatura, $fatura->tur);
        $this->assertSame(FaturaDurumu::Beklemede, $fatura->durum);
        $this->assertSame('180.00000000', $fatura->ara_toplam);
        $this->assertSame('20.00000000', $fatura->toplam_indirim);
        $this->assertSame('36.00000000', $fatura->kdv_toplam);
        $this->assertSame('216.00000000', $fatura->genel_toplam);
        $this->assertSame('216.00000000', $fatura->odenecek_tutar);
        $this->assertSame((int) $fatura->id, (int) $teklif->refresh()->faturaya_donustu_fatura_id);

        $kalem = FaturaKalemi::tenantScopeOlmadan(fn () => $fatura->kalemler()->firstOrFail());
        $this->assertSame('Kamera montaj', $kalem->aciklama);
        $this->assertTrue($kalem->hizmet_mi);
        $this->assertSame('20.00000000', $kalem->satir_indirim_tutari);
        $this->assertSame('180.00000000', $kalem->net_tutar);
        $this->assertSame('36.00000000', $kalem->kdv_tutari);
        $this->assertSame('216.00000000', $kalem->toplam);

        $tekrar = $servis->bekleyenFaturaOlustur($teklif->refresh());

        $this->assertSame((int) $fatura->id, (int) $tekrar->id);
        $this->assertSame(1, FaturaKalemi::tenantScopeOlmadan(fn () => $fatura->kalemler()->count()));
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
}
