<?php

namespace Tests\Unit\Muhasebe;

use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;
use PHPUnit\Framework\TestCase;

class FaturaTuruVeStokIadeAyrimiTest extends TestCase
{
    public function test_fatura_turleri_ayri_anahtarlar(): void
    {
        $this->assertSame('satis_iadesi', FaturaTuru::SatisIadesi->value);
        $this->assertSame('alis_iadesi', FaturaTuru::AlisIadesi->value);
    }

    public function test_stok_islem_turleri_iade_ayrimi(): void
    {
        $this->assertSame('satis_iadesi', StokHareketIslemTuru::SatisIadesi->value);
        $this->assertSame('alis_iadesi', StokHareketIslemTuru::AlisIadesi->value);
    }
}
