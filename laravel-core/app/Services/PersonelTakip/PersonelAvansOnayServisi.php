<?php

namespace App\Services\PersonelTakip;

use App\Models\Personel\PersonelAvansi;
use App\Models\Scopes\FirmaIdTenantScope;
use Illuminate\Validation\ValidationException;

final class PersonelAvansOnayServisi
{
    public function onayla(int $firmaId, int $avansId, ?int $onaylayanId = null, bool $finansaIsle = false): PersonelAvansi
    {
        $avans = $this->avansBul($firmaId, $avansId);
        $avans->forceFill([
            'durum' => 'onaylandi',
            'onay_durumu' => 'onaylandi',
            'onaylayan_id' => $onaylayanId,
            'onaylayan_kullanici_id' => $onaylayanId,
            'onay_tarihi' => now(),
        ])->save();

        if ($finansaIsle && in_array((string) $avans->odeme_kanali, ['kasa', 'banka'], true)) {
            app(PersonelFinansHareketServisi::class)->avansOdemesiniFinansaIsle($avans->refresh());
        }

        return $avans->refresh();
    }

    public function reddet(int $firmaId, int $avansId, ?int $onaylayanId = null, ?string $aciklama = null): PersonelAvansi
    {
        $avans = $this->avansBul($firmaId, $avansId);
        $avans->forceFill([
            'durum' => 'reddedildi',
            'onay_durumu' => 'reddedildi',
            'onaylayan_id' => $onaylayanId,
            'onaylayan_kullanici_id' => $onaylayanId,
            'onay_tarihi' => now(),
            'aciklama' => $aciklama ?: $avans->aciklama,
        ])->save();

        return $avans->refresh();
    }

    private function avansBul(int $firmaId, int $avansId): PersonelAvansi
    {
        $avans = PersonelAvansi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereKey($avansId)
            ->first();

        if (! $avans) {
            throw ValidationException::withMessages([
                'avans' => 'Avans kaydı bu firmaya ait değil.',
            ]);
        }

        return $avans;
    }
}
