<?php

namespace App\Filament\Resources\PlanYonetimKaynagi\Pages;

use App\Filament\Resources\PlanYonetimKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class PlanListesi extends ListRecords
{
    protected static string $resource = PlanYonetimKaynagi::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Plan ekle'),
        ];
    }
}
