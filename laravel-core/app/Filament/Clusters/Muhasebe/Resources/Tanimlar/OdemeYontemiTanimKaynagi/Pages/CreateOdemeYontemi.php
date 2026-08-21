<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\OdemeYontemiTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\Concerns\MutatesStandartMuhasebeTanimOlustur;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\OdemeYontemiTanimKaynagi;
use Filament\Resources\Pages\CreateRecord;

class CreateOdemeYontemi extends CreateRecord
{
    use MutatesStandartMuhasebeTanimOlustur;

    protected static string $resource = OdemeYontemiTanimKaynagi::class;

    protected static ?string $title = 'Ödeme yöntemi ekle';
}
