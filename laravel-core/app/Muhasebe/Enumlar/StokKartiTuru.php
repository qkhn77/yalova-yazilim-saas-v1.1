<?php

namespace App\Muhasebe\Enumlar;

enum StokKartiTuru: string
{
    case Hammadde = 'hammadde';

    case Mamul = 'mamul';

    case TicariMal = 'ticari_mal';

    case Hizmet = 'hizmet';

    case Diger = 'diger';

    case ETicaret = 'e-ticaret';

    public function etiket(): string
    {
        return match ($this) {
            self::Hammadde => 'Hammadde',
            self::Mamul => 'Mamul',
            self::TicariMal => 'Ticari mal',
            self::Hizmet => 'Hizmet',
            self::Diger => 'Diğer',
            self::ETicaret => 'E-Ticaret',
        };
    }
}
