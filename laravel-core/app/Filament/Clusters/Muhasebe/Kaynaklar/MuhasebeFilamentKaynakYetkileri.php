<?php

namespace App\Filament\Clusters\Muhasebe\Kaynaklar;

use App\Muhasebe\Guvenlik\MuhasebeFilamentErisimYardimcisi;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament Resource can* ile policy mantığına paralel: süper admin, modül ve yetki.
 * Liste/tekil görüntüleme: yalnızca “görüntüle” yetkisi olanlar değil; düzenleme yetkisi olanlar da panele girebilir
 * ({@see CariPolicy} / {@see PosHesabiPolicy} / {@see StokKartiPolicy} ile uyumlu).
 */
trait MuhasebeFilamentKaynakYetkileri
{
    /** @var array<string, bool> */
    private static array $muhasebeYetkiCache = [];

    abstract protected static function goruntuleYetkisi(): string;

    abstract protected static function olusturYetkisi(): string;

    abstract protected static function guncelleYetkisi(): string;

    abstract protected static function silYetkisi(): string;

    protected static function muhasebeYetkiliMi(string $yetkiKodu): bool
    {
        $cacheKey = static::class.'|'.$yetkiKodu;

        if (array_key_exists($cacheKey, self::$muhasebeYetkiCache)) {
            return self::$muhasebeYetkiCache[$cacheKey];
        }

        return self::$muhasebeYetkiCache[$cacheKey] = MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi($yetkiKodu);
    }

    public static function canViewAny(): bool
    {
        foreach ([
            static::goruntuleYetkisi(),
            static::olusturYetkisi(),
            static::guncelleYetkisi(),
            static::silYetkisi(),
        ] as $yetkiKodu) {
            if (static::muhasebeYetkiliMi($yetkiKodu)) {
                return true;
            }
        }

        return false;
    }

    public static function canView(Model $kayit): bool
    {
        foreach ([
            static::goruntuleYetkisi(),
            static::guncelleYetkisi(),
        ] as $yetkiKodu) {
            if (static::muhasebeYetkiliMi($yetkiKodu)) {
                return true;
            }
        }

        return false;
    }

    public static function canCreate(): bool
    {
        return static::muhasebeYetkiliMi(static::olusturYetkisi());
    }

    public static function canEdit(Model $kayit): bool
    {
        return static::muhasebeYetkiliMi(static::guncelleYetkisi());
    }

    public static function canDelete(Model $kayit): bool
    {
        return static::muhasebeYetkiliMi(static::silYetkisi());
    }
}
