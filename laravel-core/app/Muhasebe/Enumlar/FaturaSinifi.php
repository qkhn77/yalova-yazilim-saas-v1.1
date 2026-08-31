<?php

namespace App\Muhasebe\Enumlar;

enum FaturaSinifi: string
{
    case StokAlisi = 'stok_alisi';
    case HizmetAlisi = 'hizmet_alisi';
    case Gider = 'gider';

    public function etiket(): string
    {
        return match ($this) {
            self::StokAlisi => 'Stok alışı',
            self::HizmetAlisi => 'Hizmet alışı',
            self::Gider => 'Gider',
        };
    }
}
