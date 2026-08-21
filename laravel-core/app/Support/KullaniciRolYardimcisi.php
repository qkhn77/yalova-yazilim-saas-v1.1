<?php

namespace App\Support;

use App\Models\User;

/**
 * Panel / tenant / policy tarafında tekrar eden süper yönetici kontrolü.
 */
final class KullaniciRolYardimcisi
{
    public static function superAdminVeyaIsAdmin(?User $kullanici): bool
    {
        if (! $kullanici instanceof User) {
            return false;
        }

        return (bool) ($kullanici->super_admin_mi ?? false)
            || (bool) ($kullanici->is_admin ?? false);
    }
}
