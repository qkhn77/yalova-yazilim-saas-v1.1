<?php

namespace App\Muhasebe\Enumlar;

enum SenetIslemTuru: string
{
    case Giris = 'giris';

    case Cikis = 'cikis';

    case Tahsilat = 'tahsilat';

    case Odeme = 'odeme';

    case Kapatma = 'kapatma';
}
