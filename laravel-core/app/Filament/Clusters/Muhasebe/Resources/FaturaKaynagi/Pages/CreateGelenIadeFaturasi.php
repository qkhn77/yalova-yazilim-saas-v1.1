<?php

namespace App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages;

use App\Muhasebe\Enumlar\FaturaTuru;

class CreateGelenIadeFaturasi extends CreateFatura
{
    protected static ?string $title = 'Gelen İade Faturası Ekle';

    protected function varsayilanTur(): ?FaturaTuru
    {
        return FaturaTuru::AlisIadesi;
    }
}
