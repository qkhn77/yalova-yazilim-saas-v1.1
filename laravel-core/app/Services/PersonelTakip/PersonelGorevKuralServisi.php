<?php

namespace App\Services\PersonelTakip;

use App\Models\Personel\PersonelDepartmani;
use App\Models\Personel\PersonelGorevi;
use App\Models\Scopes\FirmaIdTenantScope;
use Illuminate\Validation\ValidationException;

final class PersonelGorevKuralServisi
{
    public function dogrula(PersonelGorevi $gorev): void
    {
        if (! $gorev->firma_id) {
            return;
        }

        $gorev->kod = is_string($gorev->kod) ? trim($gorev->kod) : $gorev->kod;
        $hatalar = [];

        if (blank($gorev->ad)) {
            $hatalar['ad'][] = 'Görev adı zorunludur.';
        }

        if ($gorev->departman_id && ! $this->departmanFirmayaAitMi($gorev)) {
            $hatalar['departman_id'][] = 'Seçilen departman bu firmaya ait değil.';
        }

        if (filled($gorev->kod) && $this->kodKullanimdaMi($gorev)) {
            $hatalar['kod'][] = 'Bu görev kodu aynı firmadaki başka bir görevde kullanılıyor.';
        }

        if ($gorev->varsayilan_ucret !== null && (float) $gorev->varsayilan_ucret < 0) {
            $hatalar['varsayilan_ucret'][] = 'Varsayılan ücret negatif olamaz.';
        }

        if ($hatalar !== []) {
            throw ValidationException::withMessages($hatalar);
        }
    }

    private function departmanFirmayaAitMi(PersonelGorevi $gorev): bool
    {
        return PersonelDepartmani::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $gorev->firma_id)
            ->whereKey($gorev->departman_id)
            ->exists();
    }

    private function kodKullanimdaMi(PersonelGorevi $gorev): bool
    {
        return PersonelGorevi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->withTrashed()
            ->where('firma_id', $gorev->firma_id)
            ->where('kod', $gorev->kod)
            ->when($gorev->exists, fn ($query) => $query->whereKeyNot($gorev->getKey()))
            ->exists();
    }
}
