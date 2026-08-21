<?php

namespace App\Muhasebe\Enumlar;

enum SenetHareketDurumu: string
{
    case Aktif = 'aktif';

    case Iptal = 'iptal';
}
