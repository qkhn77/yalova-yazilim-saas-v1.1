<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Muhasebe\Enumlar\FaturaTuru;

class ProformaFaturaSayfasi extends FaturaListesiFiltreliSayfasi
{
    protected static ?string $title = 'Proforma Fatura';

    protected static ?string $slug = 'faturalar/proforma-fatura';

    public static function faturaTurleri(): array
    {
        return [FaturaTuru::Proforma->value, FaturaTuru::ProformaFatura->value];
    }

    protected static function olusturmaSayfasiAnahtari(): string
    {
        return 'createProforma';
    }

    protected static function olusturmaButonEtiketi(): string
    {
        return 'Proforma Fatura Ekle';
    }
}
