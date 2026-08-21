<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class FrontIcerikCache
{
    public static function surum(string $bolum): string
    {
        return (string) Cache::get(self::surumAnahtari($bolum), '1');
    }

    public static function temizle(string $bolum): void
    {
        Cache::forever(self::surumAnahtari($bolum), self::yeniSurum());
    }

    private static function surumAnahtari(string $bolum): string
    {
        return 'front:icerik:'.$bolum.':surum';
    }

    private static function yeniSurum(): string
    {
        return str_replace([' ', '.'], '', (string) microtime());
    }
}
