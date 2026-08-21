<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeTasarimTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\Concerns\MutatesStandartMuhasebeTanimOlustur;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeTasarimTanimKaynagi;
use Filament\Resources\Pages\CreateRecord;

class CreateMuhasebeTasarim extends CreateRecord
{
    use MutatesStandartMuhasebeTanimOlustur;

    protected static string $resource = MuhasebeTasarimTanimKaynagi::class;

    protected static ?string $title = 'Tasarım ekle';
}
