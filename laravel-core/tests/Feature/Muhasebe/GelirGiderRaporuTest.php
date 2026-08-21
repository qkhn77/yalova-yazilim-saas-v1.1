<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\Fatura;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Services\MuhasebeDisaAktarimServisi;
use App\Services\TenantContextService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GelirGiderRaporuTest extends TestCase
{
    use RefreshDatabase;

    public function test_gelir_gider_ozeti_tenant_tarih_para_birimi_ve_fatura_adedini_korur(): void
    {
        $firma = $this->firmaOlustur('GGR-A');
        $digerFirma = $this->firmaOlustur('GGR-B');

        $this->faturaOlustur($firma, FaturaTuru::Giden, FaturaDurumu::Onayli, '100.00', 'TRY');
        $this->faturaOlustur($firma, FaturaTuru::Gelen, FaturaDurumu::Onayli, '40.00', 'TRY');
        $this->faturaOlustur($firma, FaturaTuru::Giden, FaturaDurumu::Taslak, '999.00', 'TRY');
        $this->faturaOlustur($digerFirma, FaturaTuru::Giden, FaturaDurumu::Onayli, '500.00', 'TRY');
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $satirlar = app(MuhasebeDisaAktarimServisi::class)->gelirGiderOzeti(
            $firma->id,
            Carbon::today()->subDay(),
            Carbon::today()->addDay(),
        );

        $this->assertCount(1, $satirlar);
        $this->assertSame('TRY', $satirlar[0]['Para Birimi']);
        $this->assertSame(2, $satirlar[0]['Fatura Adedi']);
        $this->assertSame(1, $satirlar[0]['Gelir Fatura Adedi']);
        $this->assertSame(1, $satirlar[0]['Gider Fatura Adedi']);
        $this->assertSame('100.00', $satirlar[0]['Gelir Toplam']);
        $this->assertSame('40.00', $satirlar[0]['Gider Toplam']);
        $this->assertSame('60.00', $satirlar[0]['Net']);
    }

    private function firmaOlustur(string $kod): Firma
    {
        return Firma::query()->create([
            'ad' => 'Gelir Gider '.$kod,
            'kisa_ad' => $kod,
            'firma_kodu' => $kod.'-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
    }

    private function faturaOlustur(Firma $firma, FaturaTuru $tur, FaturaDurumu $durum, string $tutar, string $paraBirimi): Fatura
    {
        return Fatura::query()->create([
            'firma_id' => $firma->id,
            'tur' => $tur->value,
            'durum' => $durum->value,
            'tarih' => now(),
            'ara_toplam' => $tutar,
            'kdv_toplam' => '0.00',
            'genel_toplam' => $tutar,
            'odenecek_tutar' => $tutar,
            'odendi_tutari' => '0.00',
            'acik_tutar' => $tutar,
            'para_birimi' => $paraBirimi,
            'doviz_kuru' => '1.00000000',
        ]);
    }
}
