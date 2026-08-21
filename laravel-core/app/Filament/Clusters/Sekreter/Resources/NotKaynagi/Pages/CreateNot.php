<?php
namespace App\Filament\Clusters\Sekreter\Resources\NotKaynagi\Pages;
use App\Filament\Clusters\Sekreter\Resources\NotKaynagi;
use App\Services\TenantContextService;
use Filament\Resources\Pages\CreateRecord;
class CreateNot extends CreateRecord
{
    protected static string $resource = NotKaynagi::class;
    protected function mutateFormDataBeforeCreate(array $data): array { $data['firma_id'] = app(TenantContextService::class)->aktifFirmaId(); $data['kullanici_id'] = auth()->id(); return $data; }
}
