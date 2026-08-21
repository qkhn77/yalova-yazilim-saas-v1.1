<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

class TumFaturalarSayfasi extends FaturaListesiFiltreliSayfasi
{
    protected static ?string $title = 'Tüm Faturalar';

    protected static ?string $slug = 'faturalar/tum-faturalar';

    public static function faturaTurleri(): array
    {
        return [];
    }
}
