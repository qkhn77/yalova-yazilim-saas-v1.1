<?php

namespace App\Filament\Clusters\TeklifYonetimi\Resources\TeklifSablonKaynagi\Pages;

use App\Filament\Clusters\TeklifYonetimi\Resources\TeklifSablonKaynagi;
use App\TeklifYonetimi\Servisler\TeklifBaskiSablonuServisi;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Cache;

class CreateTeklifSablonu extends CreateRecord
{
    protected static string $resource = TeklifSablonKaynagi::class;

    protected static ?string $title = 'Yeni Teklif Şablonu';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['firma_id'] = TeklifSablonKaynagi::aktifFirmaId();
        $data['aktif'] = (bool) ($data['aktif'] ?? true);
        if ((bool) ($data['varsayilan_mi'] ?? false)) {
            $data['aktif'] = true;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        Cache::forget('teklif_sablon_arama|'.((int) $this->record->firma_id).'|');

        if ((bool) $this->record->varsayilan_mi) {
            app(TeklifBaskiSablonuServisi::class)->varsayilanYap($this->record);
        }
    }
}
