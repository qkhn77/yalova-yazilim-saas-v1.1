<?php
namespace App\Filament\Clusters\Sekreter\Resources\GorevKaynagi\Pages;
use App\Filament\Clusters\Sekreter\Resources\GorevKaynagi;
use App\Services\TenantContextService;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Pages\CreateRecord;
class CreateGorev extends CreateRecord
{
    protected static string $resource = GorevKaynagi::class;
    protected function mutateFormDataBeforeCreate(array $data): array { $data['firma_id'] = app(TenantContextService::class)->aktifFirmaId(); $data['olusturan_kullanici_id'] = auth()->id(); return $data; }
}
