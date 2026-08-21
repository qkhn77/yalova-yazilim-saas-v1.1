<?php

namespace App\Services;

use App\Models\Setting;

final class SistemBakimModuServisi
{
    public const AKTIF_ANAHTARI = 'sistem_bakim_modu_aktif';

    public const MESAJ_ANAHTARI = 'sistem_bakim_modu_mesaj';

    public function aktifMi(): bool
    {
        return filter_var(Setting::get(self::AKTIF_ANAHTARI, false), FILTER_VALIDATE_BOOLEAN);
    }

    public function mesaj(): string
    {
        return trim((string) Setting::get(
            self::MESAJ_ANAHTARI,
            'Sistemimiz planlı bakım çalışması nedeniyle geçici olarak hizmet dışıdır.'
        ));
    }

    public function kaydet(bool $aktif, string $mesaj): void
    {
        Setting::set(self::AKTIF_ANAHTARI, $aktif ? '1' : '0', 'sistem');
        Setting::set(
            self::MESAJ_ANAHTARI,
            trim($mesaj) !== '' ? trim($mesaj) : 'Sistemimiz planlı bakım çalışması nedeniyle geçici olarak hizmet dışıdır.',
            'sistem'
        );
    }
}
