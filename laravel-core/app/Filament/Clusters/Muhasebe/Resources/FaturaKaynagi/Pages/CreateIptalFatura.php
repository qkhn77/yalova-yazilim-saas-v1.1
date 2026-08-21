<?php

namespace App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages;

use App\Muhasebe\Enumlar\FaturaTuru;

class CreateIptalFatura extends CreateFatura
{
    protected static ?string $title = 'Iptal Fatura Ekle';

    protected function varsayilanTur(): ?FaturaTuru
    {
        return FaturaTuru::IptalFatura;
    }
}
