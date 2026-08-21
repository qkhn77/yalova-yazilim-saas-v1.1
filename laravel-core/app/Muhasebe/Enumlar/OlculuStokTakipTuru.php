<?php

namespace App\Muhasebe\Enumlar;

enum OlculuStokTakipTuru: string
{
    case Standart = 'standart';
    case Uzunluk = 'uzunluk';
    case Alan = 'alan';
    case Hacim = 'hacim';
    case Agirlik = 'agirlik';

    public function olculuMu(): bool
    {
        return $this !== self::Standart;
    }

    public function anaBirimKodu(): string
    {
        return match ($this) {
            self::Standart => 'AD',
            self::Uzunluk => 'MTR',
            self::Alan => 'MTK',
            self::Hacim => 'MTQ',
            self::Agirlik => 'KGM',
        };
    }
}
