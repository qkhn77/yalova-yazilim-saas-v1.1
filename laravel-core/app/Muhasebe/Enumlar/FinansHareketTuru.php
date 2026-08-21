<?php

namespace App\Muhasebe\Enumlar;

enum FinansHareketTuru: string
{
    case Tahsilat = 'tahsilat';

    case Odeme = 'odeme';

    case Virman = 'virman';

    case Mahsup = 'mahsup';
}
