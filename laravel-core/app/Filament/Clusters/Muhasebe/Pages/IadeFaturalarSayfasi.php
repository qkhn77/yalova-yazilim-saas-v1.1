<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Muhasebe\Enumlar\FaturaTuru;

class IadeFaturalarSayfasi extends FaturaListesiFiltreliSayfasi
{
    public static function isDiscovered(): bool
    {
        return false;
    }

    protected static ?string $title = 'İade Faturalar';

    protected static ?string $slug = 'faturalar/iade-faturalar';

    public static function faturaTurleri(): array
    {
        return [FaturaTuru::SatisIadesi->value, FaturaTuru::AlisIadesi->value, FaturaTuru::IadeFatura->value];
    }
}
