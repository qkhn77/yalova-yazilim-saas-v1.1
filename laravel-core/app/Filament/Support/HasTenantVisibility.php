<?php

namespace App\Filament\Support;

use App\Models\User;
use App\Services\SidebarService;
use App\Services\TenantContextService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Filament kaynaklarında görünürlük: {@see SidebarService::menuGorunurMu()} ile aynı kurallar.
 * Cluster / özel sayfalar için erişim hizalaması: {@see SidebarService::sidebarBolumHaritasi()}.
 *
 * Kullanan sınıf en azından `$modulKodu` ve `$goruntuleYetkiKodu` static özelliklerini tanımlamalıdır
 * (trait içinde tekrar tanımlanmaz; PHP 8.3+ uyumluluğu için).
 */
trait HasTenantVisibility
{
    protected static function sidebarService(): SidebarService
    {
        return app(SidebarService::class);
    }

    protected static function tenantContext(): TenantContextService
    {
        return app(TenantContextService::class);
    }

    protected static function currentUser(): ?User
    {
        $kullanici = Auth::user();

        return $kullanici instanceof User ? $kullanici : null;
    }

    protected static function aktifFirmaId(): ?int
    {
        return static::tenantContext()->aktifFirmaId();
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (! static::goruntuleTanimiVarMi()) {
            return false;
        }

        return static::sidebarService()->menuGorunurMu(
            static::currentUser(),
            static::aktifFirmaId(),
            static::$modulKodu,
            static::$goruntuleYetkiKodu
        );
    }

    public static function canAccess(): bool
    {
        // Bu trait yalnızca Filament görünürlük/erişim kolaylığı sağlar.
        // Asıl backend güvenliği policy/middleware katmanında kalır.
        return static::shouldRegisterNavigation();
    }

    public static function canViewAny(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canView(Model $record): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canCreate(): bool
    {
        $yetkiKodu = static::$olusturYetkiKodu ?? static::$goruntuleYetkiKodu;
        if (! $yetkiKodu) {
            return false;
        }

        return static::sidebarService()->menuGorunurMu(
            static::currentUser(),
            static::aktifFirmaId(),
            static::$modulKodu,
            $yetkiKodu
        );
    }

    public static function canEdit(Model $record): bool
    {
        $yetkiKodu = static::$guncelleYetkiKodu ?? static::$goruntuleYetkiKodu;
        if (! $yetkiKodu) {
            return false;
        }

        return static::sidebarService()->menuGorunurMu(
            static::currentUser(),
            static::aktifFirmaId(),
            static::$modulKodu,
            $yetkiKodu
        );
    }

    public static function canDelete(Model $record): bool
    {
        $yetkiKodu = static::$silYetkiKodu ?? static::$goruntuleYetkiKodu;
        if (! $yetkiKodu) {
            return false;
        }

        return static::sidebarService()->menuGorunurMu(
            static::currentUser(),
            static::aktifFirmaId(),
            static::$modulKodu,
            $yetkiKodu
        );
    }

    protected static function goruntuleTanimiVarMi(): bool
    {
        return is_string(static::$goruntuleYetkiKodu) && static::$goruntuleYetkiKodu !== '';
    }
}

