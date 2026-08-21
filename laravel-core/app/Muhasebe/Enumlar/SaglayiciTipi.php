<?php

namespace App\Muhasebe\Enumlar;

enum SaglayiciTipi: string
{
    case BankaPosu = 'banka_posu';

    case OdemeKurulusu = 'odeme_kurulusu';

    public function etiket(): string
    {
        return match ($this) {
            self::BankaPosu => "Banka POS'u",
            self::OdemeKurulusu => 'Ödeme kuruluşu',
        };
    }
}
