<?php

namespace App\Filament\Clusters\TeknikServis\Resources\Concerns;

use App\Filament\Clusters\TeknikServis\Kaynaklar\TeknikServisFilamentErisimYardimcisi;
use App\Support\TeknikServisYetkiSablonlari;
use Illuminate\Database\Eloquent\Model;

/**
 * Teknik servis tanım kartları (Muhasebe standart tanım kaynağı kalıbı ile uyumlu: oluştur/sil -> tanım güncelle).
 */
trait TeknikServisTanimKaynakErisimi
{
    public static function canViewAny(): bool
    {
        return TeknikServisFilamentErisimYardimcisi::herhangiBirTeknikServisErisimiVarMi([
            TeknikServisYetkiSablonlari::TANIM_GORUNTULE,
            TeknikServisYetkiSablonlari::TANIM_GUNCELLE,
        ]);
    }

    public static function canView(Model $record): bool
    {
        return TeknikServisFilamentErisimYardimcisi::herhangiBirTeknikServisErisimiVarMi([
            TeknikServisYetkiSablonlari::TANIM_GORUNTULE,
            TeknikServisYetkiSablonlari::TANIM_GUNCELLE,
        ]);
    }

    public static function canCreate(): bool
    {
        return TeknikServisFilamentErisimYardimcisi::teknikServisYetkisiVarMi(TeknikServisYetkiSablonlari::TANIM_GUNCELLE);
    }

    public static function canEdit(Model $record): bool
    {
        return TeknikServisFilamentErisimYardimcisi::teknikServisYetkisiVarMi(TeknikServisYetkiSablonlari::TANIM_GUNCELLE);
    }

    public static function canDelete(Model $record): bool
    {
        return TeknikServisFilamentErisimYardimcisi::teknikServisYetkisiVarMi(TeknikServisYetkiSablonlari::TANIM_GUNCELLE);
    }
}
