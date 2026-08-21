<?php

namespace App\Muhasebe\Enumlar;

enum CekHareketDurumu: string
{
    case Aktif = 'aktif';

    case Iptal = 'iptal';
}
