<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Muhasebe\Enumlar\FaturaTuru;

class GidenFatura extends FaturaListesiFiltreliSayfasi
{
    protected static ?string $title = 'Giden Faturalar';

    protected static ?string $slug = 'faturalar/giden-faturalar';

    public static function faturaTurleri(): array
    {
        return [FaturaTuru::Giden->value, FaturaTuru::GidenFatura->value];
    }

    protected static function olusturmaSayfasiAnahtari(): string
    {
        return 'createGiden';
    }

    protected static function olusturmaButonEtiketi(): string
    {
        return 'Giden Fatura Ekle';
    }
}
