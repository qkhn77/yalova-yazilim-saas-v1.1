<?php

namespace App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages;

use App\Muhasebe\Enumlar\FaturaTuru;

class CreateGidenFatura extends CreateFatura
{
    protected static ?string $title = 'Giden Fatura Ekle';

    protected function varsayilanTur(): ?FaturaTuru
    {
        return FaturaTuru::Giden;
    }
}
