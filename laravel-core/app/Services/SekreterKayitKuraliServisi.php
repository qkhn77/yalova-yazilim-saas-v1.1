<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class SekreterKayitKuraliServisi
{
    public function kontrolEt(Model $kayit): void
    {
        $firmaId = (int) ($kayit->firma_id ?: app(TenantContextService::class)->aktifFirmaId());
        if ($firmaId < 1) {
            return;
        }

        $modul = app(ModulErisimService::class);
        if (filled($kayit->cari_id) && ! $modul->modulErisilebilirMi($firmaId, 'muhasebe')) {
            throw ValidationException::withMessages(['cari_id' => 'Muhasebe modülü aktif değilken Cari bağlantısı kullanılamaz.']);
        }

        if (filled($kayit->atanan_personel_id) && ! $modul->modulErisilebilirMi($firmaId, 'personel_takip')) {
            throw ValidationException::withMessages(['atanan_personel_id' => 'Personel Takip modülü aktif değilken personel atanamaz.']);
        }
    }
}
