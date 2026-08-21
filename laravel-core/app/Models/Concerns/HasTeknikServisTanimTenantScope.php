<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TeknikServisTanimFirmaScope;
use App\Models\User;
use App\Support\KullaniciRolYardimcisi;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Teknik servis tanım modelleri için kiracı kapsamı: firma_id = aktif firma VEYA firma_id NULL (global tanım).
 *
 * @phpstan-require-extends Model
 */
trait HasTeknikServisTanimTenantScope
{
    protected static int $teknikServisTanimTenantScopeBypassDerinlik = 0;

    protected static function bootHasTeknikServisTanimTenantScope(): void
    {
        static::addGlobalScope(new TeknikServisTanimFirmaScope);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $islem
     * @return T
     */
    public static function tenantScopeOlmadan(Closure $islem): mixed
    {
        static::$teknikServisTanimTenantScopeBypassDerinlik++;
        try {
            return $islem();
        } finally {
            static::$teknikServisTanimTenantScopeBypassDerinlik--;
        }
    }

    public static function tenantScopeBypassAktifMi(): bool
    {
        return static::$teknikServisTanimTenantScopeBypassDerinlik > 0;
    }

    public static function kullaniciSuperAdminMi(User $kullanici): bool
    {
        return KullaniciRolYardimcisi::superAdminVeyaIsAdmin($kullanici);
    }
}
