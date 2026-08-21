<?php

namespace App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages;

use App\Muhasebe\Enumlar\FaturaTuru;

class CreateProformaFatura extends CreateFatura
{
    protected static ?string $title = 'Proforma Fatura Ekle';

    protected function varsayilanTur(): ?FaturaTuru
    {
        return FaturaTuru::Proforma;
    }
}
