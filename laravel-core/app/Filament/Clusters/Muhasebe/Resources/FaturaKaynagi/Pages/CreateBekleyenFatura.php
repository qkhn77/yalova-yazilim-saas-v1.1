<?php

namespace App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages;

use App\Muhasebe\Enumlar\FaturaTuru;

class CreateBekleyenFatura extends CreateFatura
{
    protected static ?string $title = 'Bekleyen Fatura Ekle';

    protected function varsayilanTur(): ?FaturaTuru
    {
        return FaturaTuru::BekleyenFatura;
    }
}
