<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\DepoTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\DepoTanimKaynagi;
use Filament\Resources\Pages\EditRecord;

class EditDepo extends EditRecord
{
    protected static string $resource = DepoTanimKaynagi::class;

    protected static ?string $title = 'Depo düzenle';

    public function getSubNavigation(): array
    {
        return [];
    }
}
