<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisArizaKaynagi\Pages;

use App\Filament\Clusters\TeknikServis\Resources\TeknikServisArizaKaynagi;
use App\Filament\Clusters\TeknikServis\Resources\Concerns\HizliTanimOlusturSayfasi;
use App\Models\TeknikServis\TeknikServisCihazTanimi;

class CreateTeknikServisArizasi extends HizliTanimOlusturSayfasi
{
    protected static string $resource = TeknikServisArizaKaynagi::class;

    /**
     * @return array<int,string>
     */
    public function cihazSecenekleri(): array
    {
        return TeknikServisCihazTanimi::query()
            ->select(['id', 'ad'])
            ->where('aktif', true)
            ->orderBy('ad')
            ->limit(100)
            ->pluck('ad', 'id')
            ->all();
    }
}
