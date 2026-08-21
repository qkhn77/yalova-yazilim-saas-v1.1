<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Muhasebe\Enumlar\FaturaTuru;

class GelenFatura extends FaturaListesiFiltreliSayfasi
{
    protected static ?string $title = 'Gelen Faturalar';

    protected static ?string $slug = 'faturalar/gelen-faturalar';

    public static function faturaTurleri(): array
    {
        return [FaturaTuru::Gelen->value, FaturaTuru::GelenFatura->value];
    }

    protected static function olusturmaSayfasiAnahtari(): string
    {
        return 'createGelen';
    }

    protected static function olusturmaButonEtiketi(): string
    {
        return 'Gelen Fatura Ekle';
    }
}
