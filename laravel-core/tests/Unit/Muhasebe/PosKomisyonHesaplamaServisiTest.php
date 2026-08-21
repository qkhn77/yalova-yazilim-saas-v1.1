<?php

namespace Tests\Unit\Muhasebe;

use App\Muhasebe\Servisler\PosKomisyonHesaplamaServisi;
use PHPUnit\Framework\TestCase;

class PosKomisyonHesaplamaServisiTest extends TestCase
{
    public function test_komisyon_yuzde_hesaplanir(): void
    {
        $servis = new PosKomisyonHesaplamaServisi;

        $this->assertSame('2.50', $servis->komisyonTutariHesapla('100.00', '2.5'));
    }

    public function test_net_tahsilat_brut_eksi_komisyon(): void
    {
        $servis = new PosKomisyonHesaplamaServisi;

        $this->assertSame('97.50', $servis->netTahsilatHesapla('100.00', '2.50'));
    }
}
