<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\DepoTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\DepoTanimKaynagi;
use App\Services\TenantContextService;
use Filament\Resources\Pages\CreateRecord;

class CreateDepo extends CreateRecord
{
    protected static string $resource = DepoTanimKaynagi::class;

    protected static ?string $title = 'Depo ekle';

    public function getSubNavigation(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['firma_id'] = app(TenantContextService::class)->aktifFirmaId();

        return $data;
    }
}
