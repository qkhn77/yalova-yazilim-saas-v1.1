<?php

namespace App\Muhasebe\Enumlar;

enum FinansHareketDurumu: string
{
    case Aktif = 'aktif';

    case Iptal = 'iptal';
}
