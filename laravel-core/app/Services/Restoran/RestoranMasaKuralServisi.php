<?php

namespace App\Services\Restoran;

use App\Models\Restoran\RestoranMasasi;
use App\Models\Restoran\RestoranSalonu;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Models\Sube;
use Illuminate\Validation\ValidationException;

final class RestoranMasaKuralServisi
{
    public function dogrula(RestoranMasasi $masa): void
    {
        $masa->durum = $masa->durum ?: RestoranMasasi::DURUM_BOS;

        if (! $masa->firma_id) {
            return;
        }

        $hatalar = [];
        $sube = $this->sube($masa);
        $salon = $this->salon($masa);

        if ($masa->sube_id && ! $sube) {
            $hatalar['sube_id'][] = 'Seçilen şube bu firmaya ait değil.';
        }

        if ($masa->salon_id && ! $salon) {
            $hatalar['salon_id'][] = 'Seçilen salon bu firmaya ait değil.';
        }

        if ($sube && $salon && $salon->sube_id && (int) $salon->sube_id !== (int) $sube->id) {
            $hatalar['salon_id'][] = 'Seçilen salon masanın şubesiyle uyumlu değil.';
        }

        if (! in_array((string) $masa->durum, [
            RestoranMasasi::DURUM_BOS,
            RestoranMasasi::DURUM_DOLU,
            RestoranMasasi::DURUM_REZERVE,
            RestoranMasasi::DURUM_KAPALI,
        ], true)) {
            $hatalar['durum'][] = 'Masa durumu geçerli değil.';
        }

        if ($hatalar !== []) {
            throw ValidationException::withMessages($hatalar);
        }
    }

    private function sube(RestoranMasasi $masa): ?Sube
    {
        if (! $masa->sube_id) {
            return null;
        }

        return Sube::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $masa->firma_id)
            ->whereKey($masa->sube_id)
            ->first();
    }

    private function salon(RestoranMasasi $masa): ?RestoranSalonu
    {
        if (! $masa->salon_id) {
            return null;
        }

        return RestoranSalonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $masa->firma_id)
            ->whereKey($masa->salon_id)
            ->first();
    }
}
