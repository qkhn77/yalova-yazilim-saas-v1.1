<?php

namespace App\TeknikServis\Enumlar;

enum OdemeDurumu: string
{
    case Odenmedi = 'odenmedi';

    case Kismi = 'kismi';

    case Odendi = 'odendi';

    case Iade = 'iade';

    case Iptal = 'iptal';
}
