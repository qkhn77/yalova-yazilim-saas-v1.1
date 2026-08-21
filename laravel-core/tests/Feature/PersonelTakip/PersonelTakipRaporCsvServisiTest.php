<?php

namespace Tests\Feature\PersonelTakip;

use App\Services\PersonelTakip\PersonelRaporCsvServisi;
use Tests\TestCase;

class PersonelTakipRaporCsvServisiTest extends TestCase
{
    public function test_personel_raporu_utf8_bom_ile_csv_uretir(): void
    {
        $csv = app(PersonelRaporCsvServisi::class)->csvIcerigi([
            'kpi' => [
                'aktif_personel' => 2,
                'maas_kalan' => 1250.5,
            ],
            'personel_performansi' => [
                [
                    'ad_soyad' => 'CSV Personeli',
                    'giris_cikis_sayisi' => 3,
                    'calisma_dakika' => 480,
                    'fazla_mesai_dakika' => 60,
                    'gec_kalma_dakika' => 5,
                ],
            ],
            'restoran_performansi' => [
                'garsonlar' => [
                    [
                        'ad_soyad' => 'Garson CSV',
                        'adisyon_sayisi' => 4,
                        'ciro' => 900.0,
                    ],
                ],
            ],
            'teknik_servis_performansi' => [
                'personeller' => [
                    [
                        'ad_soyad' => 'Teknisyen CSV',
                        'gorev_sayisi' => 5,
                        'aktif_gorev_sayisi' => 1,
                        'tamamlanan_gorev_sayisi' => 4,
                    ],
                ],
            ],
        ]);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('KPI;;aktif_personel;2', $csv);
        $this->assertStringContainsString('Personel;"CSV Personeli";calisma_dakika;480', $csv);
        $this->assertStringContainsString('"Restoran Garson";"Garson CSV";ciro;900.00', $csv);
        $this->assertStringContainsString('"Teknik Servis";"Teknisyen CSV";tamamlanan_gorev_sayisi;4', $csv);
    }
}
