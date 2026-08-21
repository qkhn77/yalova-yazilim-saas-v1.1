<?php

namespace App\TeknikServis\Enumlar;

enum TeknikServisKalemMuhasebeDurumu: string
{
    case Taslak = 'taslak';

    case Faturalandi = 'faturalandi';

    case Iptal = 'iptal';
}
