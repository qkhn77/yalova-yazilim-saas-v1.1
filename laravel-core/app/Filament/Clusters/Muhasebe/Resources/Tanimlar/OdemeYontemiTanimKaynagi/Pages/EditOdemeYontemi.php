<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\OdemeYontemiTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\Concerns\MutatesStandartMuhasebeTanimGuncelle;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\OdemeYontemiTanimKaynagi;
use Filament\Resources\Pages\EditRecord;

class EditOdemeYontemi extends EditRecord
{
    use MutatesStandartMuhasebeTanimGuncelle;

    protected static string $resource = OdemeYontemiTanimKaynagi::class;

    protected static ?string $title = 'Ödeme yöntemi düzenle';
}
