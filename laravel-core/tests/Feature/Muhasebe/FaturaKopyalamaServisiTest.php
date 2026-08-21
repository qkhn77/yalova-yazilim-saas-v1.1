<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokOlcuBakiyesi;
use App\Models\User;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Muhasebe\Servisler\FaturaIslemServisi;
use App\Muhasebe\Servisler\FaturaKopyalamaServisi;
use App\Muhasebe\Servisler\FaturaOlcuKalemiServisi;
use App\Muhasebe\Servisler\StokOlcuBakiyeServisi;
use Database\Seeders\MuhasebeOlcuBirimleriSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaturaKopyalamaServisiTest extends TestCase
{
    use RefreshDatabase;

    public function test_standart_fatura_kopyasi_taslak_olur(): void
    {
        [$kaynak] = $this->standartKur();
        $kopya = app(FaturaKopyalamaServisi::class)->kopyala($kaynak);
        $this->assertNotSame($kaynak->id, $kopya->id);
        $this->assertSame(FaturaDurumu::Taslak, $kopya->durum);
        $this->assertNull($kopya->bagli_fatura_id);
    }

    public function test_olculu_fatura_kopyasi_taslak_olur(): void
    {
        [$kaynak] = $this->olculuKur();
        $kopya = app(FaturaKopyalamaServisi::class)->kopyala($kaynak);
        $this->assertSame(FaturaDurumu::Taslak, $kopya->durum);
        $this->assertCount(0, $kopya->kalemler->first()->olcuDagilimlari);
    }

    public function test_kopyalama_stok_hareketi_olusturmaz(): void
    {
        [$kaynak] = $this->standartKur();
        $kopya = app(FaturaKopyalamaServisi::class)->kopyala($kaynak);
        $this->assertSame(0, $kopya->stokHareketleri()->count());
    }

    public function test_kopyalama_olcu_bakiyesini_degistirmez(): void
    {
        [$kaynak, $bakiye] = $this->olculuKur();
        $once = $bakiye->ana_miktar;
        app(FaturaKopyalamaServisi::class)->kopyala($kaynak);
        $this->assertDatabaseHas('stok_olcu_bakiyeleri', ['id' => $bakiye->id, 'ana_miktar' => $once]);
    }

    public function test_kopyada_eski_olcu_dagilimi_tasinmaz(): void
    {
        [$kaynak] = $this->olculuKur();
        $kopya = app(FaturaKopyalamaServisi::class)->kopyala($kaynak);
        $this->assertDatabaseMissing('fatura_kalemi_olcu_dagilimlari', ['fatura_kalemi_id' => $kopya->kalemler->first()->id]);
    }

    public function test_kopyada_eski_bakiye_idsi_secili_degil(): void
    {
        [$kaynak] = $this->olculuKur();
        $kopya = app(FaturaKopyalamaServisi::class)->kopyala($kaynak);
        $this->assertNull($kopya->kalemler->first()->depo_id);
    }

    public function test_olculu_kopya_yeni_dagilim_secmeden_onaylanamaz(): void
    {
        [$kaynak] = $this->olculuKur();
        $kopya = app(FaturaKopyalamaServisi::class)->kopyala($kaynak);
        $this->expectException(\App\Muhasebe\Exceptions\IsKuraliIstisnasi::class);
        app(FaturaIslemServisi::class)->faturayiOnayla($kopya->fresh());
    }

    public function test_standart_kopya_onayda_bir_hareket_olusturur(): void
    {
        [$kaynak] = $this->standartKur();
        $kopya = app(FaturaKopyalamaServisi::class)->kopyala($kaynak);
        app(FaturaIslemServisi::class)->faturayiOnayla($kopya->fresh());
        app(FaturaIslemServisi::class)->faturayiOnayla($kopya->fresh());
        $this->assertSame(1, $kopya->stokHareketleri()->count());
    }

    public function test_olculu_alis_kopyasi_kaynak_iade_baglantisi_tasinmaz(): void
    {
        [$kaynak] = $this->olculuKur(FaturaTuru::AlisIadesi);
        $kopya = app(FaturaKopyalamaServisi::class)->kopyala($kaynak);
        $this->assertNull($kopya->bagli_fatura_id);
        $this->assertNull($kopya->kalemler->first()->kaynak_fatura_kalemi_id);
    }

    public function test_kopyalanmis_iade_eski_kaynakla_otomatik_onaylanamaz(): void
    {
        [$kaynak] = $this->olculuKur(FaturaTuru::AlisIadesi);
        $kopya = app(FaturaKopyalamaServisi::class)->kopyala($kaynak);
        $this->assertNull($kopya->bagli_fatura_id);
        $this->expectException(\App\Muhasebe\Exceptions\IsKuraliIstisnasi::class);
        app(FaturaIslemServisi::class)->faturayiOnayla($kopya->fresh());
    }

    public function test_kopya_tenant_firma_baglantisini_degistirmez(): void
    {
        [$kaynak] = $this->standartKur();
        $kopya = app(FaturaKopyalamaServisi::class)->kopyala($kaynak);
        $this->assertSame($kaynak->firma_id, $kopya->firma_id);
        $this->assertSame($kaynak->cari_id, $kopya->cari_id);
    }

    public function test_kopya_iptalinde_hareket_terslenir_ve_tekrar_iptal_nooptur(): void
    {
        [$kaynak] = $this->standartKur();
        $kopya = app(FaturaKopyalamaServisi::class)->kopyala($kaynak);
        app(FaturaIslemServisi::class)->faturayiOnayla($kopya->fresh());
        app(FaturaIslemServisi::class)->faturayiIptalEt($kopya->fresh());
        app(FaturaIslemServisi::class)->faturayiIptalEt($kopya->fresh());
        $this->assertSame(FaturaDurumu::Iptal, $kopya->fresh()->durum);
    }

    /** @return array{0:Fatura,1?:StokOlcuBakiyesi} */
    private function standartKur(): array
    {
        [$firma, $cari, $depo, $stok] = $this->temelKur();
        $fatura = $this->faturaOlustur($firma, $cari, FaturaTuru::Giden);
        FaturaKalemi::create(['firma_id' => $firma->id, 'fatura_id' => $fatura->id, 'satir_no' => 1, 'stok_id' => $stok->id, 'depo_id' => $depo->id, 'miktar' => '1', 'birim' => 'AD', 'birim_fiyat' => '100', 'net_tutar' => '100', 'toplam' => '100', 'satir_toplami' => '100', 'satir_genel_toplam' => '100', 'para_birimi' => 'TRY']);
        return [$fatura->fresh('kalemler'), $cari, $depo, $stok];
    }

    private function olculuKur(FaturaTuru $tur = FaturaTuru::Giden): array
    {
        [$firma, $cari, $depo, $stok] = $this->temelKur(true);
        $olcu = app(StokOlcuBakiyeServisi::class)->olcuOlustur($firma->id, $stok, ['kod' => '200X200', 'ad' => '200x200', 'olcu_birimi' => 'cm', 'en' => '200', 'boy' => '200']);
        $bakiye = app(StokOlcuBakiyeServisi::class)->bakiyeBulVeyaOlustur($firma->id, $stok, $olcu, $depo);
        app(StokOlcuBakiyeServisi::class)->giris($bakiye, anaMiktar: '4');
        $fatura = $this->faturaOlustur($firma, $cari, $tur);
        $kalem = FaturaKalemi::create(['firma_id' => $firma->id, 'fatura_id' => $fatura->id, 'satir_no' => 1, 'stok_id' => $stok->id, 'depo_id' => $depo->id, 'miktar' => '1', 'ana_miktar' => '4', 'birim' => 'MTK', 'birim_fiyat' => '1000', 'net_tutar' => '4000', 'toplam' => '4000', 'satir_toplami' => '4000', 'satir_genel_toplam' => '4000', 'para_birimi' => 'TRY']);
        app(FaturaOlcuKalemiServisi::class)->dagilimlariSakla($kalem, [['stok_olcusu_id' => $olcu->id, 'stok_olcu_bakiyesi_id' => $bakiye->id, 'depo_id' => $depo->id, 'islem_birimi_id' => Birim::withoutGlobalScopes()->where('kod', 'MTK')->value('id'), 'girilen_miktar' => '1']], false);
        return [$fatura->fresh(['kalemler.olcuDagilimlari']), $bakiye->fresh()];
    }

    private function temelKur(bool $olculu = false): array
    {
        $firma = Firma::create(['ad' => 'Kopya Firma', 'kisa_ad' => 'KF', 'firma_kodu' => 'KF-'.uniqid(), 'durum' => Firma::DURUM_AKTIF, 'onaylandi_mi' => true]);
        $this->actingAs(User::create(['name' => 'Kopya User', 'email' => uniqid().'@test.local', 'password' => bcrypt('x'), 'super_admin_mi' => true]));
        $cari = Cari::create(['firma_id' => $firma->id, 'kod' => 'C-1', 'ad' => 'Cari', 'tur' => CariTuru::Musteri->value, 'durum' => CariDurumu::Aktif->value]);
        $depo = Depo::create(['firma_id' => $firma->id, 'kod' => 'D1', 'ad' => 'Depo', 'aktif_mi' => true]);
        $this->seed(MuhasebeOlcuBirimleriSeeder::class);
        $m2 = Birim::withoutGlobalScopes()->where('kod', 'MTK')->firstOrFail();
        $ad = Birim::withoutGlobalScopes()->where('kod', 'AD')->firstOrFail();
        $stok = StokKarti::create(['firma_id' => $firma->id, 'kod' => 'K-'.uniqid(), 'ad' => 'Stok', 'tur' => StokKartiTuru::TicariMal->value, 'durum' => HesapDurumu::Aktif->value, 'stok_takip' => true, 'stok_miktari' => $olculu ? '0' : '1', 'depo_id' => $depo->id, 'para_birimi' => 'TRY', 'olculu_takip_turu' => $olculu ? 'alan' : 'standart', 'ana_birim_id' => $olculu ? $m2->id : null, 'ikincil_birim_id' => $olculu ? $ad->id : null]);
        return [$firma, $cari, $depo, $stok];
    }

    private function faturaOlustur(Firma $firma, Cari $cari, FaturaTuru $tur): Fatura
    {
        return Fatura::create(['firma_id' => $firma->id, 'cari_id' => $cari->id, 'tur' => $tur->value, 'durum' => FaturaDurumu::Taslak->value, 'tarih' => now(), 'ara_toplam' => '100', 'kdv_toplam' => '0', 'genel_toplam' => '100', 'toplam_indirim' => '0', 'odenecek_tutar' => '100', 'odendi_tutari' => '0', 'acik_tutar' => '100', 'para_birimi' => 'TRY', 'doviz_kuru' => '1']);
    }
}
