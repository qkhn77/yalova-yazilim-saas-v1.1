<?php

namespace App\Models\Concerns;

use App\Models\Scopes\FirmaIdTenantScope;
use App\Models\User;
use App\Support\KullaniciRolYardimcisi;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Global {@see FirmaIdTenantScope} + konsol/test bypass yardımcıları.
 *
 * @phpstan-require-extends Model
 */
trait HasFirmaTenantScope
{
    protected static int $tenantScopeBypassDerinlik = 0;

    protected static function bootHasFirmaTenantScope(): void
    {
        static::addGlobalScope(new FirmaIdTenantScope);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $islem
     * @return T
     */
    public static function tenantScopeOlmadan(Closure $islem): mixed
    {
        static::$tenantScopeBypassDerinlik++;
        try {
            return $islem();
        } finally {
            static::$tenantScopeBypassDerinlik--;
        }
    }

    public static function tenantScopeBypassAktifMi(): bool
    {
        return static::$tenantScopeBypassDerinlik > 0;
    }

    public static function kullaniciSuperAdminMi(User $kullanici): bool
    {
        return KullaniciRolYardimcisi::superAdminVeyaIsAdmin($kullanici);
    }
}
