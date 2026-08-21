<?php

namespace App\Filament\Clusters\TeknikServis\Resources\Concerns;

use App\Filament\Clusters\TeknikServis\Kaynaklar\TeknikServisFilamentErisimYardimcisi;
use App\Support\TeknikServisYetkiSablonlari;
use Illuminate\Database\Eloquent\Model;

/**
 * Servis kayıtları ({@see \App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi}) için CRUD yetkileri.
 */
trait TeknikServisKayitKaynakErisimi
{
    public static function canViewAny(): bool
    {
        return TeknikServisFilamentErisimYardimcisi::herhangiBirTeknikServisErisimiVarMi([
            TeknikServisYetkiSablonlari::GORUNTULE,
            TeknikServisYetkiSablonlari::OLUSTUR,
            TeknikServisYetkiSablonlari::GUNCELLE,
            TeknikServisYetkiSablonlari::SIL,
        ]);
    }

    public static function canView(Model $record): bool
    {
        return TeknikServisFilamentErisimYardimcisi::herhangiBirTeknikServisErisimiVarMi([
            TeknikServisYetkiSablonlari::GORUNTULE,
            TeknikServisYetkiSablonlari::OLUSTUR,
            TeknikServisYetkiSablonlari::GUNCELLE,
        ]);
    }

    public static function canCreate(): bool
    {
        return TeknikServisFilamentErisimYardimcisi::teknikServisYetkisiVarMi(TeknikServisYetkiSablonlari::OLUSTUR);
    }

    public static function canEdit(Model $record): bool
    {
        return TeknikServisFilamentErisimYardimcisi::teknikServisYetkisiVarMi(TeknikServisYetkiSablonlari::GUNCELLE);
    }

    public static function canDelete(Model $record): bool
    {
        return TeknikServisFilamentErisimYardimcisi::teknikServisYetkisiVarMi(TeknikServisYetkiSablonlari::SIL);
    }
}
