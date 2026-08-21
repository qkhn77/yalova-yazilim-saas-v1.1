<?php

namespace App\Filament\Clusters\ETicaret\Pages;

use App\Filament\Clusters\ETicaret\ETicaretTaslakSayfa;

class VaryasyonYonetimiSayfasi extends ETicaretTaslakSayfa
{
    protected static ?string $title = null;

    protected static ?string $slug = 'varyasyon-yonetimi';

    protected static ?string $gerekenYetkiKodu = 'e_ticaret_varyasyon.goruntule';

    public function getTitle(): string
    {
        return __('filament.ecommerce.variation.title');
    }
}
