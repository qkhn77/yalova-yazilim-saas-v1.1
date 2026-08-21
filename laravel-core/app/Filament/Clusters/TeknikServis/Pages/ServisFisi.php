<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

class ServisFisi extends ServisFormu
{
    protected static ?string $title = 'Servis Fişi';

    protected static ?string $slug = 'belgeler/servis-fisi';

    protected function sablonTuru(): string
    {
        return 'servis_fisi';
    }
}
