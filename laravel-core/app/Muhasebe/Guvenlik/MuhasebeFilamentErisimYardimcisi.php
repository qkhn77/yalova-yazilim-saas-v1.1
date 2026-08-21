<?php

namespace App\Muhasebe\Guvenlik;

use App\Models\User;
use App\Services\SidebarService;
use App\Services\TenantContextService;
use App\Support\KullaniciRolYardimcisi;
use Illuminate\Support\Facades\Auth;

/**
 * Filament muhasebe kaynakları ile {@see MuhasebeSayfaErisimleri} için ortak menü/yetki kontrolü.
 */
final class MuhasebeFilamentErisimYardimcisi
{
    public static function muhasebeYetkisiVarMi(string $yetkiKodu): bool
    {
        $kullanici = Auth::user();
        if (! $kullanici instanceof User) {
            return false;
        }

        if (KullaniciRolYardimcisi::superAdminVeyaIsAdmin($kullanici)) {
            return true;
        }

        $firmaId = app(TenantContextService::class)->aktifFirmaId();

        return app(SidebarService::class)->menuGorunurMu($kullanici, $firmaId, 'muhasebe', $yetkiKodu);
    }

    /**
     * @param  non-empty-array<int, string>  $yetkiKodlari
     */
    public static function herhangiBirMuhasebeYetkisiVarMi(array $yetkiKodlari): bool
    {
        foreach ($yetkiKodlari as $kod) {
            if (self::muhasebeYetkisiVarMi($kod)) {
                return true;
            }
        }

        return false;
    }
}
