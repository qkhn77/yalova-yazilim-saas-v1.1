<?php

namespace App\TeknikServis\Enumlar;

enum MusteriOnayDurumu: string
{
    case Beklemede = 'beklemede';

    case Onaylandi = 'onaylandi';

    case Reddedildi = 'reddedildi';
}
