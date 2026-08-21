<?php

namespace App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages;

use App\Muhasebe\Enumlar\FaturaTuru;

class CreateGidenIadeFaturasi extends CreateFatura
{
    protected static ?string $title = 'Giden İade Faturası Ekle';

    protected function varsayilanTur(): ?FaturaTuru
    {
        return FaturaTuru::SatisIadesi;
    }
}
