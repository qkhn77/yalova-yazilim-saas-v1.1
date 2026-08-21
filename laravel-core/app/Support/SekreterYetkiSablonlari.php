<?php

namespace App\Support;

final class SekreterYetkiSablonlari
{
    public const GORUNTULE = 'sekreter.goruntule';
    public const OLUSTUR = 'sekreter.olustur';
    public const GUNCELLE = 'sekreter.guncelle';
    public const SIL = 'sekreter.sil';

    public static function tumu(): array
    {
        return [self::GORUNTULE, self::OLUSTUR, self::GUNCELLE, self::SIL];
    }
}
