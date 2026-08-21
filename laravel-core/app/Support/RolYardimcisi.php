<?php

namespace App\Support;

use App\Models\Rol;

/**
 * Tenant varsayılan rolleri. Seed'de `yonetici` kodu yoktur; `firma_yoneticisi` kullanılmalıdır.
 */
final class RolYardimcisi
{
    /**
     * Firma içi yönetici için önerilen sistem rolü (önce Firma Yöneticisi, yoksa Firma Sahibi).
     */
    public static function varsayilanFirmaYoneticisiRolId(): ?int
    {
        return Rol::query()->where('kod', 'firma_yoneticisi')->value('id')
            ?? Rol::query()->where('kod', 'firma_sahibi')->value('id');
    }
}
