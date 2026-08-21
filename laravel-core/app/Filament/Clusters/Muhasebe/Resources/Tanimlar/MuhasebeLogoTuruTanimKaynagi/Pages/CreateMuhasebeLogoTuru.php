<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeLogoTuruTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\Concerns\MutatesStandartMuhasebeTanimOlustur;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeLogoTuruTanimKaynagi;
use Filament\Resources\Pages\CreateRecord;

class CreateMuhasebeLogoTuru extends CreateRecord
{
    use MutatesStandartMuhasebeTanimOlustur;

    protected static string $resource = MuhasebeLogoTuruTanimKaynagi::class;

    protected static ?string $title = 'Logo türü ekle';
}
