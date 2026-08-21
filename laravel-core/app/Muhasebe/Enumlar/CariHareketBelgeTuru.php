<?php

namespace App\Muhasebe\Enumlar;

enum CariHareketBelgeTuru: string
{
    case Fatura = 'fatura';

    case Satis = 'satis';

    case Odeme = 'odeme';

    case Tahsilat = 'tahsilat';

    case Mahsup = 'mahsup';

    /** Manuel / entegrasyon iade satırları için (fatura iadeleri fatura üzerinden yönetilir). */
    case Iade = 'iade';

    public function etiket(): string
    {
        return match ($this) {
            self::Fatura => 'Fatura',
            self::Satis => 'Satis',
            self::Odeme => 'Ödeme',
            self::Tahsilat => 'Tahsilat',
            self::Mahsup => 'Mahsup',
            self::Iade => 'İade',
        };
    }
}
