<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\BirimTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\BirimTanimKaynagi;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\Concerns\MutatesStandartMuhasebeTanimOlustur;
use Filament\Resources\Pages\CreateRecord;

class CreateBirim extends CreateRecord
{
    use MutatesStandartMuhasebeTanimOlustur;

    protected static string $resource = BirimTanimKaynagi::class;

    protected static ?string $title = 'Birim ekle';
}
