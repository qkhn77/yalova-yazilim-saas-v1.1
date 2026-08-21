<?php
namespace App\Filament\Clusters\Sekreter\Resources\RandevuKaynagi\Pages;
use App\Filament\Clusters\Sekreter\Resources\RandevuKaynagi;
use App\Services\TenantContextService;
use Filament\Resources\Pages\CreateRecord;
class CreateRandevu extends CreateRecord
{
    protected static string $resource = RandevuKaynagi::class;
    protected function mutateFormDataBeforeCreate(array $data): array { $data['firma_id'] = app(TenantContextService::class)->aktifFirmaId(); $data['olusturan_kullanici_id'] = auth()->id(); return $data; }
}
