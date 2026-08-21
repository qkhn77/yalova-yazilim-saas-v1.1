<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeVaryantTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\Concerns\MutatesStandartMuhasebeTanimOlustur;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeVaryantTanimKaynagi;
use Filament\Resources\Pages\CreateRecord;

class CreateMuhasebeVaryant extends CreateRecord
{
    use MutatesStandartMuhasebeTanimOlustur;

    protected static string $resource = MuhasebeVaryantTanimKaynagi::class;

    protected static ?string $title = 'Varyant ekle';
}
