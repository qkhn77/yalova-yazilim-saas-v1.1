<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Muhasebe\Enumlar\FaturaTuru;

class GelenIadeFaturasiSayfasi extends FaturaListesiFiltreliSayfasi
{
    protected static ?string $title = 'Gelen İade Faturaları';

    protected static ?string $slug = 'faturalar/gelen-iade-faturalari';

    public static function faturaTurleri(): array
    {
        return [FaturaTuru::AlisIadesi->value];
    }

    public static function dogrudanIadeEdilenFaturaTurleri(): array
    {
        return [FaturaTuru::Gelen->value];
    }

    protected static function olusturmaSayfasiAnahtari(): string
    {
        return 'createGelenIade';
    }

    protected static function olusturmaButonEtiketi(): string
    {
        return 'Gelen İade Faturası Ekle';
    }
}
