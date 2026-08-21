<?php

namespace App\Filament\Resources\DenetimKayidiKaynagi\Pages;

use App\Filament\Resources\DenetimKayidiKaynagi;
use Filament\Resources\Pages\ListRecords;

class DenetimKayitlariListesi extends ListRecords
{
    protected static string $resource = DenetimKayidiKaynagi::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
