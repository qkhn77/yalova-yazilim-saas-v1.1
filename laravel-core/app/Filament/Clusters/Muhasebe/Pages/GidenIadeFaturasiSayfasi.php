<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Muhasebe\Enumlar\FaturaTuru;

class GidenIadeFaturasiSayfasi extends FaturaListesiFiltreliSayfasi
{
    protected static ?string $title = 'Giden İade Faturaları';

    protected static ?string $slug = 'faturalar/giden-iade-faturalari';

    public static function faturaTurleri(): array
    {
        return [FaturaTuru::SatisIadesi->value, FaturaTuru::IadeFatura->value];
    }

    public static function dogrudanIadeEdilenFaturaTurleri(): array
    {
        return [FaturaTuru::Giden->value];
    }

    protected static function olusturmaSayfasiAnahtari(): string
    {
        return 'createGidenIade';
    }

    protected static function olusturmaButonEtiketi(): string
    {
        return 'Giden İade Faturası Ekle';
    }
}
