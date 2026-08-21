<?php

namespace Tests\Unit\Muhasebe;

use App\Muhasebe\Enumlar\FaturaTuru;
use Tests\TestCase;

class FaturaTuruSozluguTest extends TestCase
{
    public function test_alias_turler_kanonik_degere_eslenir(): void
    {
        $this->assertSame(FaturaTuru::Giden, FaturaTuru::GidenFatura->kanonik());
        $this->assertSame(FaturaTuru::Gelen, FaturaTuru::GelenFatura->kanonik());
        $this->assertSame(FaturaTuru::Gider, FaturaTuru::GiderFaturasi->kanonik());
        $this->assertSame(FaturaTuru::Proforma, FaturaTuru::ProformaFatura->kanonik());
        $this->assertSame(FaturaTuru::SatisIadesi, FaturaTuru::IadeFatura->kanonik());
    }

    public function test_cari_ve_stok_yon_sozlugu_tutarlidir(): void
    {
        $this->assertSame('alacak', FaturaTuru::GidenFatura->cariYonu());
        $this->assertSame('borc', FaturaTuru::GelenFatura->cariYonu());
        $this->assertSame('borc', FaturaTuru::GiderFaturasi->cariYonu());
        $this->assertSame('borc', FaturaTuru::IadeFatura->cariYonu());
        $this->assertSame('yok', FaturaTuru::ProformaFatura->cariYonu());
        $this->assertSame('yok', FaturaTuru::BekleyenFatura->cariYonu());

        $this->assertSame('cikis', FaturaTuru::GidenFatura->stokYonu());
        $this->assertSame('giris', FaturaTuru::GelenFatura->stokYonu());
        $this->assertSame('giris', FaturaTuru::IadeFatura->stokYonu());
        $this->assertSame('yok', FaturaTuru::ProformaFatura->stokYonu());
    }
}
