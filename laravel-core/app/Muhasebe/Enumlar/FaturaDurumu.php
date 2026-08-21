<?php

namespace App\Muhasebe\Enumlar;

enum FaturaDurumu: string
{
    case Taslak = 'taslak';

    case Onayli = 'onayli';

    case Beklemede = 'beklemede';

    case Iptal = 'iptal';

    case Iade = 'iade';
}
