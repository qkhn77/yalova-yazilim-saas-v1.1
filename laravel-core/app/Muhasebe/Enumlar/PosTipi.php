<?php

namespace App\Muhasebe\Enumlar;

enum PosTipi: string
{
    case FizikiPos = 'fiziki_pos';

    case SanalPos = 'sanal_pos';

    case MobilPos = 'mobil_pos';

    public function etiket(): string
    {
        return match ($this) {
            self::FizikiPos => 'Fiziki POS',
            self::SanalPos => 'Sanal POS',
            self::MobilPos => 'Mobil POS',
        };
    }
}
