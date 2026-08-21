<?php

namespace App\Muhasebe\Enumlar;

enum SenetDurumu: string
{
    case Portfoyde = 'portfoyde';

    case Verildi = 'verildi';

    case Odendi = 'odendi';

    case IadeEdildi = 'iade_edildi';

    case ImhaEdildi = 'imha_edildi';

    case Iptal = 'iptal';
}
