<?php

namespace App\Services\Restoran;

use App\Models\Restoran\RestoranSalonu;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Models\Sube;
use Illuminate\Validation\ValidationException;

final class RestoranSalonKuralServisi
{
    public function dogrula(RestoranSalonu $salon): void
    {
        if (! $salon->firma_id || ! $salon->sube_id) {
            return;
        }

        $subeVar = Sube::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $salon->firma_id)
            ->whereKey($salon->sube_id)
            ->exists();

        if (! $subeVar) {
            throw ValidationException::withMessages([
                'sube_id' => ['Seçilen şube bu firmaya ait değil.'],
            ]);
        }
    }
}
