<?php

namespace App\Muhasebe\Enumlar;

enum CekDurumu: string
{
    case Portfoyde = 'portfoyde';

    case Verildi = 'verildi';

    case Iptal = 'iptal';
}
