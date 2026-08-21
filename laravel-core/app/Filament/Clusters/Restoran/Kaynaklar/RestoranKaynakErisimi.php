<?php

namespace App\Filament\Clusters\Restoran\Kaynaklar;

use Illuminate\Database\Eloquent\Model;

trait RestoranKaynakErisimi
{
    abstract protected static function goruntuleYetkisi(): string;

    abstract protected static function olusturYetkisi(): string;

    abstract protected static function guncelleYetkisi(): string;

    abstract protected static function silYetkisi(): string;

    public static function canViewAny(): bool
    {
        return RestoranFilamentErisimYardimcisi::herhangiBirRestoranErisimiVarMi([
            static::goruntuleYetkisi(),
            static::olusturYetkisi(),
            static::guncelleYetkisi(),
            static::silYetkisi(),
        ]);
    }

    public static function canView(Model $record): bool
    {
        if (! RestoranFilamentErisimYardimcisi::kayitAktifFirmayaAitMi($record)) {
            return false;
        }

        return RestoranFilamentErisimYardimcisi::herhangiBirRestoranErisimiVarMi([
            static::goruntuleYetkisi(),
            static::guncelleYetkisi(),
        ]);
    }

    public static function canCreate(): bool
    {
        return RestoranFilamentErisimYardimcisi::restoranYetkisiVarMi(static::olusturYetkisi());
    }

    public static function canEdit(Model $record): bool
    {
        if (! RestoranFilamentErisimYardimcisi::kayitAktifFirmayaAitMi($record)) {
            return false;
        }

        return RestoranFilamentErisimYardimcisi::restoranYetkisiVarMi(static::guncelleYetkisi());
    }

    public static function canDelete(Model $record): bool
    {
        if (! RestoranFilamentErisimYardimcisi::kayitAktifFirmayaAitMi($record)) {
            return false;
        }

        return RestoranFilamentErisimYardimcisi::restoranYetkisiVarMi(static::silYetkisi());
    }
}
