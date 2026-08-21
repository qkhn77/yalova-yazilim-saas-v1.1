<?php

namespace App\Services\PersonelTakip;

use App\Models\Personel\PersonelDepartmani;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Models\Sube;
use Illuminate\Validation\ValidationException;

final class PersonelDepartmanKuralServisi
{
    public function dogrula(PersonelDepartmani $departman): void
    {
        if (! $departman->firma_id) {
            return;
        }

        $departman->kod = is_string($departman->kod) ? trim($departman->kod) : $departman->kod;
        $hatalar = [];

        if (blank($departman->ad)) {
            $hatalar['ad'][] = 'Departman adı zorunludur.';
        }

        if ($departman->sube_id && ! $this->subeFirmayaAitMi($departman)) {
            $hatalar['sube_id'][] = 'Seçilen şube bu firmaya ait değil.';
        }

        if (filled($departman->kod) && $this->kodKullanimdaMi($departman)) {
            $hatalar['kod'][] = 'Bu departman kodu aynı firmadaki başka bir departmanda kullanılıyor.';
        }

        if ($hatalar !== []) {
            throw ValidationException::withMessages($hatalar);
        }
    }

    private function subeFirmayaAitMi(PersonelDepartmani $departman): bool
    {
        return Sube::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $departman->firma_id)
            ->whereKey($departman->sube_id)
            ->exists();
    }

    private function kodKullanimdaMi(PersonelDepartmani $departman): bool
    {
        return PersonelDepartmani::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->withTrashed()
            ->where('firma_id', $departman->firma_id)
            ->where('kod', $departman->kod)
            ->when($departman->exists, fn ($query) => $query->whereKeyNot($departman->getKey()))
            ->exists();
    }
}
