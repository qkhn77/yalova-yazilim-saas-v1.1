<?php

namespace App\TeknikServis\Enumlar;

enum TeknikServisMuhasebeIslemTipi: string
{
    case Satis = 'satis';

    case Gider = 'gider';

    case Tahsilat = 'tahsilat';
}
