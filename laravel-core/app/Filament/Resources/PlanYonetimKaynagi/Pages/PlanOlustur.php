<?php

namespace App\Filament\Resources\PlanYonetimKaynagi\Pages;

use App\Filament\Resources\PlanYonetimKaynagi;
use App\Models\Plan;
use App\Support\DenetimYardimcisi;
use Filament\Resources\Pages\CreateRecord;

class PlanOlustur extends CreateRecord
{
    protected static string $resource = PlanYonetimKaynagi::class;

    protected function afterCreate(): void
    {
        DenetimYardimcisi::kaydet(
            'plan_olusturuldu',
            Plan::class,
            (int) $this->record->id,
            null,
            null,
            ['ad' => $this->record->ad, 'kod' => $this->record->kod]
        );
    }
}
