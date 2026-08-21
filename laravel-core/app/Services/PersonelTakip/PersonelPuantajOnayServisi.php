<?php

namespace App\Services\PersonelTakip;

use App\Models\Personel\PersonelGirisCikisi;
use App\Models\Scopes\FirmaIdTenantScope;
use Illuminate\Validation\ValidationException;

final class PersonelPuantajOnayServisi
{
    public function onayla(int $firmaId, int $kayitId, ?int $onaylayanId = null): PersonelGirisCikisi
    {
        $kayit = $this->kayitBul($firmaId, $kayitId);

        if (! $kayit->giris_at || ! $kayit->cikis_at) {
            throw ValidationException::withMessages([
                'cikis_at' => 'Çıkış zamanı olmayan puantaj kaydı onaylanamaz.',
            ]);
        }

        $kayit->forceFill([
            'onay_durumu' => 'onaylandi',
            'onaylayan_id' => $onaylayanId,
        ])->save();

        return $kayit->refresh();
    }

    public function reddet(int $firmaId, int $kayitId, ?int $onaylayanId = null, ?string $aciklama = null): PersonelGirisCikisi
    {
        $kayit = $this->kayitBul($firmaId, $kayitId);

        $kayit->forceFill([
            'onay_durumu' => 'reddedildi',
            'onaylayan_id' => $onaylayanId,
            'aciklama' => $aciklama ?: $kayit->aciklama,
        ])->save();

        return $kayit->refresh();
    }

    private function kayitBul(int $firmaId, int $kayitId): PersonelGirisCikisi
    {
        $kayit = PersonelGirisCikisi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereKey($kayitId)
            ->first();

        if (! $kayit) {
            throw ValidationException::withMessages([
                'kayit' => 'Puantaj kaydı bu firmaya ait değil.',
            ]);
        }

        return $kayit;
    }
}
