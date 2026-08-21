<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe\MuhasebeTaslakSayfa;
use App\Support\MuhasebeYetkiSablonlari;

class MarkaModelTasarimVaryantSayfasi extends MuhasebeTaslakSayfa
{
    /** @deprecated STEP 15.1.1 — ayrı tanım kaynaklarına bölündü */
    public static function isDiscovered(): bool
    {
        return false;
    }

    protected static ?string $title = 'Marka / Model / Tasarım / Varyant';

    protected static ?string $slug = 'tanimlar/marka-model-tasarim-varyant';

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::TANIM_GORUNTULE;
    }
}
