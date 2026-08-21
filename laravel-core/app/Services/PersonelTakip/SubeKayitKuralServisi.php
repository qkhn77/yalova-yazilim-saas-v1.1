<?php

namespace App\Services\PersonelTakip;

use App\Models\Scopes\FirmaIdTenantScope;
use App\Models\Sube;
use Illuminate\Validation\ValidationException;

final class SubeKayitKuralServisi
{
    public function dogrula(Sube $sube): void
    {
        if (! $sube->firma_id) {
            return;
        }

        $sube->kod = is_string($sube->kod) ? trim($sube->kod) : $sube->kod;
        $hatalar = [];

        if (blank($sube->ad)) {
            $hatalar['ad'][] = 'Şube adı zorunludur.';
        }

        if (filled($sube->kod)) {
            $kodKullanimda = Sube::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->withTrashed()
                ->where('firma_id', $sube->firma_id)
                ->where('kod', $sube->kod)
                ->when($sube->exists, fn ($query) => $query->whereKeyNot($sube->getKey()))
                ->exists();

            if ($kodKullanimda) {
                $hatalar['kod'][] = 'Bu şube kodu aynı firmadaki başka bir şubede kullanılıyor.';
            }
        }

        if ($hatalar !== []) {
            throw ValidationException::withMessages($hatalar);
        }
    }
}
