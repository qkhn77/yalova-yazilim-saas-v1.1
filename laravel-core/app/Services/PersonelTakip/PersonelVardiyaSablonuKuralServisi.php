<?php

namespace App\Services\PersonelTakip;

use App\Models\Personel\PersonelVardiyaSablonu;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Models\Sube;
use Illuminate\Validation\ValidationException;

final class PersonelVardiyaSablonuKuralServisi
{
    public function dogrula(PersonelVardiyaSablonu $sablon): void
    {
        if (! $sablon->firma_id) {
            return;
        }

        $hatalar = [];

        if ($sablon->sube_id) {
            $subeVarMi = Sube::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->where('firma_id', $sablon->firma_id)
                ->whereKey($sablon->sube_id)
                ->exists();

            if (! $subeVarMi) {
                $hatalar['sube_id'][] = 'Seçilen şube bu firmaya ait değil.';
            }
        }

        if ($sablon->baslangic_saati && $sablon->bitis_saati && $sablon->baslangic_saati === $sablon->bitis_saati) {
            $hatalar['bitis_saati'][] = 'Vardiya başlangıç ve bitiş saati aynı olamaz.';
        }

        $adKullanimda = PersonelVardiyaSablonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $sablon->firma_id)
            ->where('ad', $sablon->ad)
            ->when($sablon->sube_id, fn ($query) => $query->where('sube_id', $sablon->sube_id), fn ($query) => $query->whereNull('sube_id'))
            ->when($sablon->exists, fn ($query) => $query->whereKeyNot($sablon->getKey()))
            ->exists();

        if ($adKullanimda) {
            $hatalar['ad'][] = 'Bu vardiya şablonu adı aynı kapsamda kullanılıyor.';
        }

        if ($hatalar !== []) {
            throw ValidationException::withMessages($hatalar);
        }
    }
}
