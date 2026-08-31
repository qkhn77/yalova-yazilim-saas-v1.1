<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\ParaBirimi;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;

class FaturaParaBirimiDogrulamaServisi
{
    public function dogrula(int $firmaId, int $cariId, string $faturaParaBirimi): Cari
    {
        $cari = Cari::query()
            ->where('firma_id', $firmaId)
            ->whereKey($cariId)
            ->first();

        if (! $cari) {
            throw new IsKuraliIstisnasi('Fatura carisi aktif firmaya ait olmalıdır.');
        }

        $faturaParaBirimi = strtoupper(trim($faturaParaBirimi));

        $gecerli = ParaBirimi::tenantScopeOlmadan(fn (): bool => ParaBirimi::query()
            ->withoutGlobalScopes()
            ->gorunurFirmaIle($firmaId)
            ->where('kod', $faturaParaBirimi)
            ->where('aktif_mi', true)
            ->exists());
        if (! $gecerli) {
            throw new IsKuraliIstisnasi('Fatura para birimi firma için aktif bir para birimi olmalıdır.');
        }

        return $cari;
    }
}
