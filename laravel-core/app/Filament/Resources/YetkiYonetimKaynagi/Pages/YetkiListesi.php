<?php

namespace App\Filament\Resources\YetkiYonetimKaynagi\Pages;

use App\Filament\Resources\YetkiYonetimKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class YetkiListesi extends ListRecords
{
    protected static string $resource = YetkiYonetimKaynagi::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Yetki ekle'),
        ];
    }
}
