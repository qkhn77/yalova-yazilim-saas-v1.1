<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Muhasebe\Enumlar\FaturaTuru;

class GiderFaturasiSayfasi extends FaturaListesiFiltreliSayfasi
{
    protected static ?string $title = 'Gider Faturası';

    protected static ?string $slug = 'faturalar/gider-faturasi';

    public static function faturaTurleri(): array
    {
        return [FaturaTuru::Gider->value, FaturaTuru::GiderFaturasi->value];
    }

    protected static function olusturmaSayfasiAnahtari(): string
    {
        return 'createGider';
    }

    protected static function olusturmaButonEtiketi(): string
    {
        return 'Gider Faturası Ekle';
    }
}
