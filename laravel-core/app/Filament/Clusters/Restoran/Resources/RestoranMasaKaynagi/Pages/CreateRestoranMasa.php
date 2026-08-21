<?php

namespace App\Filament\Clusters\Restoran\Resources\RestoranMasaKaynagi\Pages;

use App\Filament\Clusters\Restoran\Resources\RestoranMasaKaynagi;
use Filament\Resources\Pages\CreateRecord;

class CreateRestoranMasa extends CreateRecord
{
    protected static string $resource = RestoranMasaKaynagi::class;

    protected function getRedirectUrl(): string
    {
        return RestoranMasaKaynagi::getUrl('index');
    }
}
