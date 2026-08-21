<?php

namespace App\Services\Restoran;

use App\Models\Restoran\RestoranMenuKategorisi;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Models\Sube;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RestoranMenuKategoriKuralServisi
{
    public function dogrula(RestoranMenuKategorisi $kategori): void
    {
        if (! $kategori->firma_id) {
            return;
        }

        $kategori->slug = $kategori->slug ?: Str::slug((string) $kategori->ad);
        $kategori->slug = $kategori->slug !== '' ? $kategori->slug : 'kategori';

        $hatalar = [];

        if (trim((string) $kategori->ad) === '') {
            $hatalar['ad'][] = 'Kategori adı boş olamaz.';
        }

        if ($kategori->sube_id && ! $this->subeVarMi((int) $kategori->firma_id, (int) $kategori->sube_id)) {
            $hatalar['sube_id'][] = 'Seçilen şube bu firmaya ait değil.';
        }

        if ($this->slugKullaniliyorMu($kategori)) {
            $hatalar['slug'][] = 'Bu kategori kısa adı firma içinde kullanılıyor.';
        }

        if ($hatalar !== []) {
            throw ValidationException::withMessages($hatalar);
        }
    }

    private function subeVarMi(int $firmaId, int $subeId): bool
    {
        return Sube::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereKey($subeId)
            ->exists();
    }

    private function slugKullaniliyorMu(RestoranMenuKategorisi $kategori): bool
    {
        return RestoranMenuKategorisi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $kategori->firma_id)
            ->where('slug', $kategori->slug)
            ->when($kategori->exists, fn ($query) => $query->whereKeyNot($kategori->getKey()))
            ->exists();
    }
}
