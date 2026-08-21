<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Demo/prod ortamında users tablosunda deleted_at henüz yokken sorguların kırılmaması için.
 * Kolon migration ile eklendikten sonra aynı kod yolu soft delete ile uyumlu çalışır.
 */
final class KullaniciTablosuYardimcisi
{
    public static function usersAktifMiKolonuVarMi(): bool
    {
        try {
            return SaaSemaYardimcisi::kolonVarMi((new User)->getTable(), 'aktif_mi');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function usersDeletedAtKolonuVarMi(): bool
    {
        try {
            return SaaSemaYardimcisi::kolonVarMi((new User)->getTable(), 'deleted_at');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * users.deleted_at kolonu varsa silinmemiş kayıt şartını ekler.
     */
    public static function kullaniciSilinmemisFiltresiUygula(Builder $sorgu, ?string $tablo = null): void
    {
        if (! self::usersDeletedAtKolonuVarMi()) {
            return;
        }

        $tablo = $tablo ?? (new User)->getTable();
        $sorgu->whereNull($tablo.'.deleted_at');
    }

    /**
     * users.aktif_mi kolonu varsa yalnız aktif kullanıcıları sorgular.
     * Migration'ı henüz uygulanmamış kurulumlarda giriş sorgusunu kırmaz.
     */
    public static function kullaniciAktifFiltresiUygula(Builder $sorgu, ?string $tablo = null): void
    {
        if (! self::usersAktifMiKolonuVarMi()) {
            return;
        }

        $tablo = $tablo ?? (new User)->getTable();
        $sorgu->where($tablo.'.aktif_mi', true);
    }
}
