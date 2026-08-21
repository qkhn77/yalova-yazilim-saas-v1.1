<?php

namespace App\Services\PersonelTakip;

use App\Models\Personel\PersonelIzni;
use App\Models\Scopes\FirmaIdTenantScope;
use Illuminate\Validation\ValidationException;

final class PersonelIzinOnayServisi
{
    public function onayla(int $firmaId, int $izinId, ?int $onaylayanId = null): PersonelIzni
    {
        $izin = $this->izinBul($firmaId, $izinId);
        $izin->forceFill([
            'durum' => 'onaylandi',
            'onay_durumu' => 'onaylandi',
            'onaylayan_id' => $onaylayanId,
            'onay_at' => now(),
        ])->save();

        return $izin->refresh();
    }

    public function reddet(int $firmaId, int $izinId, ?int $onaylayanId = null, ?string $aciklama = null): PersonelIzni
    {
        $izin = $this->izinBul($firmaId, $izinId);
        $izin->forceFill([
            'durum' => 'reddedildi',
            'onay_durumu' => 'reddedildi',
            'onaylayan_id' => $onaylayanId,
            'onay_at' => now(),
            'aciklama' => $aciklama ?: $izin->aciklama,
        ])->save();

        return $izin->refresh();
    }

    private function izinBul(int $firmaId, int $izinId): PersonelIzni
    {
        $izin = PersonelIzni::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereKey($izinId)
            ->first();

        if (! $izin) {
            throw ValidationException::withMessages([
                'izin' => 'İzin kaydı bu firmaya ait değil.',
            ]);
        }

        return $izin;
    }
}
