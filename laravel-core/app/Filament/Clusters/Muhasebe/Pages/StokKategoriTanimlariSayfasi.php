<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe\MuhasebeTaslakSayfa;
use App\Support\MuhasebeYetkiSablonlari;

/**
 * Tanımlar menüsündeki stok kategorileri (stok bölümündeki liste ile ayrı rota).
 */
class StokKategoriTanimlariSayfasi extends MuhasebeTaslakSayfa
{
    protected static ?string $title = 'Stok Kategorileri (Tanım)';

    protected static ?string $slug = 'tanimlar/stok-kategorileri';

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::TANIM_GORUNTULE;
    }
}
