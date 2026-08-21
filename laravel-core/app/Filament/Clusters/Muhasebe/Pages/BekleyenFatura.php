<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;

class BekleyenFatura extends FaturaListesiFiltreliSayfasi
{
    protected static ?string $title = 'Bekleyen Faturalar';

    protected static ?string $slug = 'faturalar/bekleyen-faturalar';

    public static function faturaTurleri(): array
    {
        return [FaturaTuru::BekleyenFatura->value];
    }

    public static function faturaDurumlari(): array
    {
        return [FaturaDurumu::Beklemede->value];
    }

    protected static function olusturmaSayfasiAnahtari(): string
    {
        return 'createBekleyen';
    }

    protected static function olusturmaButonEtiketi(): string
    {
        return 'Bekleyen Fatura Ekle';
    }
}
