<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeMalzemeTuruTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\Concerns\MutatesStandartMuhasebeTanimOlustur;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeMalzemeTuruTanimKaynagi;
use Filament\Resources\Pages\CreateRecord;

class CreateMuhasebeMalzemeTuru extends CreateRecord
{
    use MutatesStandartMuhasebeTanimOlustur;

    protected static string $resource = MuhasebeMalzemeTuruTanimKaynagi::class;

    protected static ?string $title = 'Malzeme türü ekle';
}
