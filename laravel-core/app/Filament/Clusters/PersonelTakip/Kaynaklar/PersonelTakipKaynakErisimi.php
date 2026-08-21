<?php

namespace App\Filament\Clusters\PersonelTakip\Kaynaklar;

use Illuminate\Database\Eloquent\Model;

trait PersonelTakipKaynakErisimi
{
    abstract protected static function goruntuleYetkisi(): string;

    abstract protected static function olusturYetkisi(): string;

    abstract protected static function guncelleYetkisi(): string;

    abstract protected static function silYetkisi(): string;

    public static function canViewAny(): bool
    {
        return PersonelTakipFilamentErisimYardimcisi::herhangiBirPersonelErisimiVarMi([
            static::goruntuleYetkisi(),
            static::olusturYetkisi(),
            static::guncelleYetkisi(),
            static::silYetkisi(),
        ]);
    }

    public static function canView(Model $record): bool
    {
        if (! PersonelTakipFilamentErisimYardimcisi::kayitAktifFirmayaAitMi($record)) {
            return false;
        }

        return PersonelTakipFilamentErisimYardimcisi::herhangiBirPersonelErisimiVarMi([
            static::goruntuleYetkisi(),
            static::guncelleYetkisi(),
        ]);
    }

    public static function canCreate(): bool
    {
        return PersonelTakipFilamentErisimYardimcisi::personelYetkisiVarMi(static::olusturYetkisi());
    }

    public static function canEdit(Model $record): bool
    {
        if (! PersonelTakipFilamentErisimYardimcisi::kayitAktifFirmayaAitMi($record)) {
            return false;
        }

        return PersonelTakipFilamentErisimYardimcisi::personelYetkisiVarMi(static::guncelleYetkisi());
    }

    public static function canDelete(Model $record): bool
    {
        if (! PersonelTakipFilamentErisimYardimcisi::kayitAktifFirmayaAitMi($record)) {
            return false;
        }

        return PersonelTakipFilamentErisimYardimcisi::personelYetkisiVarMi(static::silYetkisi());
    }
}
