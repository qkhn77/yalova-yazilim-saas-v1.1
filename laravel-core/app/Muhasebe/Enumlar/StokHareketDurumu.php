<?php

namespace App\Muhasebe\Enumlar;

enum StokHareketDurumu: string
{
    case Aktif = 'aktif';

    case Iptal = 'iptal';
}
