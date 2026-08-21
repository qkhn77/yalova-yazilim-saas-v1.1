<?php

namespace App\Support;

use App\Models\Firma;

class FirmaKoduUretici
{
    /**
     * Benzersiz firma_kodu üretir (silinmiş kayıtlar dahil çakışma kontrolü).
     */
    public static function birSonraki(): string
    {
        do {
            $kod = 'F'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 7));
        } while (Firma::withTrashed()->where('firma_kodu', $kod)->exists());

        return $kod;
    }
}
