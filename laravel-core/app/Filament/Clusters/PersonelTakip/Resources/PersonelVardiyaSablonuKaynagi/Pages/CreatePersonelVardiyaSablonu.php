<?php

namespace App\Filament\Clusters\PersonelTakip\Resources\PersonelVardiyaSablonuKaynagi\Pages;

use App\Filament\Clusters\PersonelTakip\Resources\PersonelVardiyaSablonuKaynagi;
use Filament\Resources\Pages\CreateRecord;

class CreatePersonelVardiyaSablonu extends CreateRecord
{
    protected static string $resource = PersonelVardiyaSablonuKaynagi::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
