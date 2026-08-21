<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;

/**
 * Migration eksik ortamlarda Filament SaaS ekranlarının 500 vermemesi için tablo varlığı kontrolleri.
 */
final class SaaSemaYardimcisi
{
    /** @var array<string, bool> */
    private static array $tabloCache = [];

    /** @var array<string, bool> */
    private static array $kolonCache = [];

    /** @var array<string, array<int, string>> */
    private static array $kolonListesiCache = [];

    public static function kolonVarMi(string $tabloAdi, string $kolonAdi): bool
    {
        $cacheKey = $tabloAdi.'.'.$kolonAdi;

        if (array_key_exists($cacheKey, self::$kolonCache)) {
            return self::$kolonCache[$cacheKey];
        }

        try {
            if (! array_key_exists($tabloAdi, self::$kolonListesiCache)) {
                self::$kolonListesiCache[$tabloAdi] = self::kaliciSchemaCacheKullanilsin()
                    ? Cache::remember(
                        self::schemaCacheKey('kolonlar', $tabloAdi),
                        now()->addMinutes(10),
                        fn (): array => Schema::getColumnListing($tabloAdi)
                    )
                    : Schema::getColumnListing($tabloAdi);
            }

            return self::$kolonCache[$cacheKey] = in_array($kolonAdi, self::$kolonListesiCache[$tabloAdi], true);
        } catch (\Throwable) {
            return self::$kolonCache[$cacheKey] = false;
        }
    }

    public static function tabloVarMi(string $tabloAdi): bool
    {
        if (array_key_exists($tabloAdi, self::$tabloCache)) {
            return self::$tabloCache[$tabloAdi];
        }

        try {
            return self::$tabloCache[$tabloAdi] = self::kaliciSchemaCacheKullanilsin()
                ? (bool) Cache::remember(
                    self::schemaCacheKey('tablo', $tabloAdi),
                    now()->addMinutes(10),
                    fn (): bool => Schema::hasTable($tabloAdi)
                )
                : Schema::hasTable($tabloAdi);
        } catch (\Throwable) {
            return self::$tabloCache[$tabloAdi] = false;
        }
    }

    private static function kaliciSchemaCacheKullanilsin(): bool
    {
        return ! app()->runningInConsole() && ! app()->runningUnitTests();
    }

    private static function schemaCacheKey(string $tur, string $anahtar): string
    {
        $baglanti = (string) config('database.default', 'default');
        $veritabani = (string) config("database.connections.$baglanti.database", 'default');

        return 'saas-schema:'.$baglanti.':'.$veritabani.':'.$tur.':'.$anahtar;
    }

    public static function firmalarTablosuVarMi(): bool
    {
        return self::tabloVarMi('firmalar');
    }

    public static function planlarTablosuVarMi(): bool
    {
        return self::tabloVarMi('planlar');
    }

    public static function firmaKullanicilariTablosuVarMi(): bool
    {
        return self::tabloVarMi('firma_kullanicilari');
    }

    public static function firmaKullanicilariOnayDurumuKolonuVarMi(): bool
    {
        return self::kolonVarMi('firma_kullanicilari', 'onay_durumu');
    }

    public static function firmaModulleriTablosuVarMi(): bool
    {
        return self::tabloVarMi('firma_modulleri');
    }

    public static function firmaAbonelikleriTablosuVarMi(): bool
    {
        return self::tabloVarMi('firma_abonelikleri');
    }

    public static function planModulleriTablosuVarMi(): bool
    {
        return self::tabloVarMi('plan_modulleri');
    }

    public static function modullerTablosuVarMi(): bool
    {
        return self::tabloVarMi('moduller');
    }

    public static function rollerTablosuVarMi(): bool
    {
        return self::tabloVarMi('roller');
    }

    public static function yetkilerTablosuVarMi(): bool
    {
        return self::tabloVarMi('yetkiler');
    }

    public static function rolYetkileriTablosuVarMi(): bool
    {
        return self::tabloVarMi('rol_yetkileri');
    }

    public static function kullaniciYetkileriTablosuVarMi(): bool
    {
        return self::tabloVarMi('kullanici_yetkileri');
    }
}
