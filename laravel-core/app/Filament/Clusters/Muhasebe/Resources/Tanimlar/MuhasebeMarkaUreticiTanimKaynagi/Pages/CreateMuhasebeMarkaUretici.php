<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeMarkaUreticiTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeMarkaUreticiTanimKaynagi;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\Concerns\MutatesStandartMuhasebeTanimOlustur;
use Filament\Resources\Pages\CreateRecord;

class CreateMuhasebeMarkaUretici extends CreateRecord
{
    use MutatesStandartMuhasebeTanimOlustur;

    protected static string $resource = MuhasebeMarkaUreticiTanimKaynagi::class;

    protected static ?string $title = 'Marka üretici ekle';
}
