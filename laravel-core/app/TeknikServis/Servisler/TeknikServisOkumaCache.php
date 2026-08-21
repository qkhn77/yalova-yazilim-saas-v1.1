<?php

namespace App\TeknikServis\Servisler;

use App\Services\TenantContextService;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

final class TeknikServisOkumaCache
{
    private const VERSION_KEY = 'teknik_servis:okuma:versiyon';
    private const CACHE_PREFIX = 'teknik_servis:okuma';
    private const CACHE_TTL_SECONDS = 300;

    /** @var array<string, mixed> */
    private static array $istekCache = [];

    /**
     * @template T
     *
     * @param  Closure(): T  $yukle
     * @return T
     */
    public function remember(string $anahtar, Closure $yukle): mixed
    {
        $versiyon = $this->versiyon();
        $kapsam = $this->kapsamAnahtari();
        $istekAnahtari = $versiyon.'|'.$kapsam.'|'.$anahtar;

        if (array_key_exists($istekAnahtari, self::$istekCache)) {
            return self::$istekCache[$istekAnahtari];
        }

        return self::$istekCache[$istekAnahtari] = Cache::remember(
            self::CACHE_PREFIX.':v'.$versiyon.':'.$kapsam.':'.$anahtar,
            self::CACHE_TTL_SECONDS,
            $yukle
        );
    }

    public function temizle(): void
    {
        Cache::forever(self::VERSION_KEY, $this->versiyon() + 1);

        self::$istekCache = [];
    }

    public function istekCacheTemizle(): void
    {
        self::$istekCache = [];
    }

    private function versiyon(): int
    {
        $versiyon = Cache::get(self::VERSION_KEY, 1);

        return is_numeric($versiyon) ? max(1, (int) $versiyon) : 1;
    }

    private function kapsamAnahtari(): string
    {
        $tenantContext = app(TenantContextService::class);
        $kullanici = Auth::user();
        $superAdminMi = $kullanici
            ? (int) ((bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false))
            : 0;

        return implode(':', [
            'u'.(int) ($kullanici?->id ?? 0),
            'f'.(int) ($tenantContext->aktifFirmaId() ?? 0),
            'r'.(int) ($tenantContext->aktifRolId() ?? 0),
            's'.$superAdminMi,
        ]);
    }
}
