<?php

namespace Tests\Feature\Muhasebe;

use App\Filament\Clusters\Muhasebe\Pages\BekleyenFatura;
use App\Filament\Clusters\Muhasebe\Pages\GelenFatura;
use App\Filament\Clusters\Muhasebe\Pages\GelenIadeFaturasiSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\GidenFatura;
use App\Filament\Clusters\Muhasebe\Pages\GidenIadeFaturasiSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\GiderFaturasiSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\IptalFatura;
use App\Filament\Clusters\Muhasebe\Pages\ProformaFaturaSayfasi;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateBekleyenFatura;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateGelenFatura;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateGelenIadeFaturasi;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateGidenFatura;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateGidenIadeFaturasi;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateGiderFaturasi;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateIptalFatura;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateProformaFatura;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Enumlar\FaturaDurumu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

class FaturaTurleriSadelestirmeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ui_nihai_turler_alias_icermez(): void
    {
        $degerler = array_map(fn (FaturaTuru $t) => $t->value, FaturaTuru::uiNihaiTurler());

        $this->assertSame([
            FaturaTuru::Gelen->value,
            FaturaTuru::Giden->value,
            FaturaTuru::BekleyenFatura->value,
            FaturaTuru::IptalFatura->value,
            FaturaTuru::SatisIadesi->value,
            FaturaTuru::AlisIadesi->value,
            FaturaTuru::Proforma->value,
            FaturaTuru::Gider->value,
        ], $degerler);

        $this->assertNotContains(FaturaTuru::GelenFatura->value, $degerler);
        $this->assertNotContains(FaturaTuru::GidenFatura->value, $degerler);
        $this->assertNotContains(FaturaTuru::ProformaFatura->value, $degerler);
        $this->assertNotContains(FaturaTuru::GiderFaturasi->value, $degerler);
    }

    public function test_slug_haritasi_yeni_iade_sayfalarina_ayrilir(): void
    {
        $this->assertSame('giden-iade-faturalari', FaturaKaynagi::slugPathFromTuru(FaturaTuru::SatisIadesi->value));
        $this->assertSame('gelen-iade-faturalari', FaturaKaynagi::slugPathFromTuru(FaturaTuru::AlisIadesi->value));
        $this->assertSame('giden-iade-faturalari', FaturaKaynagi::slugPathFromTuru(FaturaTuru::IadeFatura->value));
    }

    public function test_tur_sayfalari_dogru_create_route_anahtarina_sahiptir(): void
    {
        $this->assertSame(FaturaKaynagi::getUrl('createGelen'), $this->protectedStaticCall(GelenFatura::class, 'olusturmaSayfasiAnahtari', true));
        $this->assertSame(FaturaKaynagi::getUrl('createGiden'), $this->protectedStaticCall(GidenFatura::class, 'olusturmaSayfasiAnahtari', true));
        $this->assertSame(FaturaKaynagi::getUrl('createBekleyen'), $this->protectedStaticCall(BekleyenFatura::class, 'olusturmaSayfasiAnahtari', true));
        $this->assertSame(FaturaKaynagi::getUrl('createIptal'), $this->protectedStaticCall(IptalFatura::class, 'olusturmaSayfasiAnahtari', true));
        $this->assertSame(FaturaKaynagi::getUrl('createGidenIade'), $this->protectedStaticCall(GidenIadeFaturasiSayfasi::class, 'olusturmaSayfasiAnahtari', true));
        $this->assertSame(FaturaKaynagi::getUrl('createGelenIade'), $this->protectedStaticCall(GelenIadeFaturasiSayfasi::class, 'olusturmaSayfasiAnahtari', true));
        $this->assertSame(FaturaKaynagi::getUrl('createProforma'), $this->protectedStaticCall(ProformaFaturaSayfasi::class, 'olusturmaSayfasiAnahtari', true));
        $this->assertSame(FaturaKaynagi::getUrl('createGider'), $this->protectedStaticCall(GiderFaturasiSayfasi::class, 'olusturmaSayfasiAnahtari', true));
    }

    public function test_tur_bazli_create_sayfalari_varsayilan_tur_doner(): void
    {
        $this->assertSame(FaturaTuru::Gelen, $this->createPageTur(CreateGelenFatura::class));
        $this->assertSame(FaturaTuru::Giden, $this->createPageTur(CreateGidenFatura::class));
        $this->assertSame(FaturaTuru::BekleyenFatura, $this->createPageTur(CreateBekleyenFatura::class));
        $this->assertSame(FaturaTuru::IptalFatura, $this->createPageTur(CreateIptalFatura::class));
        $this->assertSame(FaturaTuru::SatisIadesi, $this->createPageTur(CreateGidenIadeFaturasi::class));
        $this->assertSame(FaturaTuru::AlisIadesi, $this->createPageTur(CreateGelenIadeFaturasi::class));
        $this->assertSame(FaturaTuru::Proforma, $this->createPageTur(CreateProformaFatura::class));
        $this->assertSame(FaturaTuru::Gider, $this->createPageTur(CreateGiderFaturasi::class));
    }

    public function test_giden_fatura_olusturmada_tur_bos_gelse_de_varsayilanla_doldurulur(): void
    {
        $method = new ReflectionMethod(CreateGidenFatura::class, 'mutateFormDataBeforeCreate');
        $method->setAccessible(true);

        $sayfa = new CreateGidenFatura();
        $out = $method->invoke($sayfa, [
            'firma_id' => 1,
            'tur' => null,
            'kalemler' => [],
            'para_birimi' => 'TRY',
        ]);

        $this->assertSame(FaturaTuru::Giden->value, $out['tur']);
    }

    public function test_giden_fatura_olusturmada_formda_secili_tur_korunur(): void
    {
        $method = new ReflectionMethod(CreateGidenFatura::class, 'mutateFormDataBeforeCreate');
        $method->setAccessible(true);

        $sayfa = new CreateGidenFatura();
        $out = $method->invoke($sayfa, [
            'firma_id' => 1,
            'tur' => FaturaTuru::Gelen->value,
            'kalemler' => [],
            'para_birimi' => 'TRY',
        ]);

        $this->assertSame(FaturaTuru::Gelen->value, $out['tur']);
    }

    public function test_onayli_olusturmada_kalem_iliskisi_onaydan_once_kaydedilecek_sekilde_korunur(): void
    {
        $sayfa = new CreateGidenFatura();
        $method = new ReflectionMethod(CreateGidenFatura::class, 'mutateFormDataBeforeCreate');
        $method->setAccessible(true);

        $out = $method->invoke($sayfa, [
            'firma_id' => 1,
            'tur' => FaturaTuru::Giden->value,
            'durum' => 'onayli',
            'kalemler' => [[
                'kalem_tipi' => 'hizmet_kalemi',
                'miktar' => 1,
                'birim_fiyat' => 100,
                'kdv_orani' => 20,
            ]],
            'para_birimi' => 'TRY',
        ]);

        $this->assertSame(FaturaDurumu::Taslak->value, $out['durum']);
        $this->assertCount(1, $out['kalemler']);

        $formSource = file_get_contents(app_path('Filament/Clusters/Muhasebe/Resources/FaturaKaynagi.php'));
        $this->assertStringContainsString('saveRelationshipsWhenDisabled', $formSource);
        $this->assertStringContainsString('faturaOlusturmaRotasiMi', $formSource);
    }

    public function test_tur_alani_sadece_tur_sayfalarinda_kilitlenir(): void
    {
        $method = new ReflectionMethod(FaturaKaynagi::class, 'turAlaniKilitliMi');
        $method->setAccessible(true);

        app()->instance('request', Request::create('/admin/muhasebe/fatura-kaynagis/create', 'GET'));
        $this->assertFalse($method->invoke(null));

        app()->instance('request', Request::create('/admin/muhasebe/fatura-kaynagis/create/giden-fatura', 'GET'));
        $this->assertTrue($method->invoke(null));

        app()->instance('request', Request::create('/admin/muhasebe/fatura-kaynagis/create/gelen-fatura', 'GET'));
        $this->assertTrue($method->invoke(null));
    }

    private function createPageTur(string $sinif): FaturaTuru
    {
        $sayfa = new $sinif;
        $method = new ReflectionMethod($sinif, 'varsayilanTur');
        $method->setAccessible(true);

        return $method->invoke($sayfa);
    }

    private function protectedStaticCall(string $sinif, string $method, bool $url = false): string
    {
        $r = new ReflectionMethod($sinif, $method);
        $r->setAccessible(true);
        $deger = $r->invoke(null);

        return $url ? FaturaKaynagi::getUrl($deger) : (string) $deger;
    }
}
