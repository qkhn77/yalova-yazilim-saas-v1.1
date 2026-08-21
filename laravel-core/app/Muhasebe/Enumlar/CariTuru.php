<?php

namespace App\Muhasebe\Enumlar;

enum CariTuru: string
{
    case Musteri = 'musteri';

    case ETicaret = 'e_ticaret';

    case Tedarikci = 'tedarikci';

    case Bayi = 'bayi';

    case Personel = 'personel';

    public function etiket(): string
    {
        return match ($this) {
            self::Musteri => 'Müşteri',
            self::ETicaret => 'E-Ticaret',
            self::Tedarikci => 'Tedarikçi',
            self::Bayi => 'Bayi',
            self::Personel => 'Personel',
        };
    }
}
