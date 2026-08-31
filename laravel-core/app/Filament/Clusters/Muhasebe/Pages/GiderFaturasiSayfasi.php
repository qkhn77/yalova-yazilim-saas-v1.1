<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Enumlar\FaturaSinifi;

class GiderFaturasiSayfasi extends FaturaListesiFiltreliSayfasi
{
    protected static ?string $title = 'Gider Faturası';

    protected static ?string $slug = 'faturalar/gider-faturasi';

    public static function faturaTurleri(): array
    {
        return [
            FaturaTuru::Gelen->value,
            FaturaTuru::GelenFatura->value,
            FaturaTuru::Gider->value,
            FaturaTuru::GiderFaturasi->value,
        ];
    }

    public static function faturaSiniflari(): array
    {
        return [FaturaSinifi::Gider->value];
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
