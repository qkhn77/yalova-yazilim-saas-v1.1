<?php

namespace App\TeknikServis\Enumlar;

enum TeknikServisMuhasebeSenkronDurumu: string
{
    case Beklemede = 'beklemede';

    case Basarili = 'basarili';

    case Hata = 'hata';

    case Iptal = 'iptal';
}
