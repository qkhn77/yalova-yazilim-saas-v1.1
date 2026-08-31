<?php

namespace App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages;

use App\Muhasebe\Enumlar\FaturaTuru;

class CreateGiderFaturasi extends CreateFatura
{
    protected static ?string $title = 'Gider Faturası Ekle';

    protected function varsayilanTur(): ?FaturaTuru
    {
        return FaturaTuru::Gelen;
    }
}
