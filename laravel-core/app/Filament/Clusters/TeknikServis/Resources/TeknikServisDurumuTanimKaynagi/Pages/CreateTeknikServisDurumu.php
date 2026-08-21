<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisDurumuTanimKaynagi\Pages;

use App\Filament\Clusters\TeknikServis\Resources\TeknikServisDurumuTanimKaynagi;
use App\Filament\Clusters\TeknikServis\Resources\Concerns\HizliTanimOlusturSayfasi;

class CreateTeknikServisDurumu extends HizliTanimOlusturSayfasi
{
    protected static string $resource = TeknikServisDurumuTanimKaynagi::class;

    /**
     * @return array<string,string>
     */
    public function bayrakAlanlari(): array
    {
        return [
            'is_fiyat_verildi' => 'Fiyat verildi',
            'is_teslim_edildi' => 'Teslim edildi',
            'is_iptal' => 'İptal',
            'is_iade' => 'İade',
        ];
    }
}
