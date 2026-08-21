<?php

namespace Tests\Feature\Urun;

use App\Models\Ecommerce\EcommercePazaryeriEntegrasyon;
use App\Models\Firma;
use App\Services\EcommercePazaryeriSiparisCekmeServisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PazaryeriSiparisCekmeServisiTest extends TestCase
{
    use RefreshDatabase;

    private function firmaOlustur(string $ad = 'Pazaryeri Firma'): Firma
    {
        return Firma::query()->create([
            'ad' => $ad,
            'kisa_ad' => 'PF',
            'firma_kodu' => 'PF-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
    }

    public function test_adaptor_uzerinden_siparis_cekimi_calisir(): void
    {
        $firma = $this->firmaOlustur();

        EcommercePazaryeriEntegrasyon::query()->create([
            'firma_id' => $firma->id,
            'pazaryeri_kodu' => 'amazon',
            'pazaryeri_adi' => 'Amazon',
            'aktif_mi' => true,
            'siparis_cekme_aktif' => true,
            'siparis_cekme_periyodu' => 30,
            'max_deneme' => 3,
            'kimlik_bilgileri' => [
                'api_key' => 'test-key',
                'api_secret' => 'test-secret',
                'satici_id' => '123',
            ],
            'ayarlar' => [
                'siparis_endpoint' => 'https://example.test/orders',
            ],
        ]);

        Http::fake([
            'https://example.test/orders*' => Http::response([
                'orders' => [
                    [
                        'orderNumber' => 'AMZ-1',
                        'totalPrice' => 120.50,
                        'currencyCode' => 'TRY',
                        'status' => 'created',
                    ],
                ],
            ], 200),
        ]);

        $ozet = app(EcommercePazaryeriSiparisCekmeServisi::class)->calistir($firma->id, 'amazon');

        $this->assertSame(1, (int) $ozet['islenen']);
        $this->assertSame(1, (int) $ozet['basarili']);
        $this->assertSame(1, (int) $ozet['import_edilen']);
        $this->assertSame(0, (int) $ozet['hatali']);
    }

    public function test_mock_siparis_adedi_varsa_mock_fallback_calisir(): void
    {
        $firma = $this->firmaOlustur('Mock Firma');

        EcommercePazaryeriEntegrasyon::query()->create([
            'firma_id' => $firma->id,
            'pazaryeri_kodu' => 'n11',
            'pazaryeri_adi' => 'N11',
            'aktif_mi' => true,
            'siparis_cekme_aktif' => true,
            'siparis_cekme_periyodu' => 30,
            'max_deneme' => 3,
            'kimlik_bilgileri' => [
                'api_key' => 'test-key',
                'api_secret' => 'test-secret',
            ],
            'ayarlar' => [
                'mock_siparis_adedi' => 2,
            ],
        ]);

        $ozet = app(EcommercePazaryeriSiparisCekmeServisi::class)->calistir($firma->id, 'n11');

        $this->assertSame(1, (int) $ozet['islenen']);
        $this->assertSame(1, (int) $ozet['basarili']);
        $this->assertSame(2, (int) $ozet['import_edilen']);
        $this->assertSame(0, (int) $ozet['hatali']);
    }
}

