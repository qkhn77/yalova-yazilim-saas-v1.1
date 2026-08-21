<?php

namespace App\Muhasebe\Enumlar;

enum StokBelgeTuru: string
{
    case Acilis = 'acilis';

    case Fatura = 'fatura';

    case Sayim = 'sayim';

    case Duzeltme = 'duzeltme';

    case Transfer = 'transfer';

    case RestoranAdisyon = 'restoran_adisyon';
}
