<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisKayitliCihaziKaynagi\Pages;

use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKayitliCihaziKaynagi;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Models\TeknikServis\TeknikServisKayitliCihazi;
use Illuminate\Validation\ValidationException;
use Filament\Resources\Pages\EditRecord;

class EditTeknikServisKayitliCihazi extends EditRecord
{
    protected static string $resource = TeknikServisKayitliCihaziKaynagi::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $ayniCihaz = TeknikServisKayitliCihazi::query()
            ->where('id', '<>', $this->record->getKey())
            ->where('cari_id', $data['cari_id'] ?? null)
            ->where('cihaz_id', $data['cihaz_id'] ?? null)
            ->where('marka_id', $data['marka_id'] ?? null)
            ->where('model_no', $data['model_no'] ?? null)
            ->where('seri_no', $data['seri_no'] ?? null)
            ->first();
        if ($ayniCihaz) {
            throw ValidationException::withMessages([
                'seri_no' => 'Bu bilgilerle kayıtlı başka bir cihaz var ('.$ayniCihaz->cihaz_no.'). Mükerrer cihaz oluşmaması için mevcut cihazı düzenleyin.',
            ]);
        }

        $data['guncelleyen_id'] = auth()->id();

        return $data;
    }

    protected function afterSave(): void
    {
        $alanlar = [
            'cari_id' => $this->record->cari_id,
            'cihaz_id' => $this->record->cihaz_id,
            'marka_id' => $this->record->marka_id,
            'model_no' => $this->record->model_no,
            'seri_no' => $this->record->seri_no,
        ];

        TeknikServisKaydi::query()
            ->where('kayitli_cihaz_id', $this->record->getKey())
            ->update($alanlar + ['guncelleyen_id' => auth()->id(), 'updated_at' => now()]);
    }
}
