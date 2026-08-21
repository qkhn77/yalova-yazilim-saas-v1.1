<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\StokKarti;
use Illuminate\Support\Collection;

class StokIzlemeServisi
{
    /**
     * @return Collection<int, StokKarti>
     */
    public function stokNegatifDurumlariGetir(int $firmaId): Collection
    {
        // Açık firma_id ile rapor: tenant session zorunlu olmadan (komut / izleme).
        return StokKarti::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('negative_flag', true)
            ->orderBy('id')
            ->get();
    }
}
