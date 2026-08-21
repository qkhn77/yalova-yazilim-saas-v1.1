<?php

namespace App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages;

use App\Muhasebe\Enumlar\FaturaTuru;

class CreateGelenFatura extends CreateFatura
{
    protected static ?string $title = 'Gelen Fatura Ekle';

    protected function varsayilanTur(): ?FaturaTuru
    {
        return FaturaTuru::Gelen;
    }
}
