<?php

namespace App\TeklifYonetimi\Guvenlik;

use App\Models\User;
use App\Services\SidebarService;
use App\Services\TenantContextService;
use App\Support\KullaniciRolYardimcisi;
use Illuminate\Support\Facades\Auth;

final class TeklifFilamentErisimYardimcisi
{
    public static function teklifYetkisiVarMi(string $yetkiKodu): bool
    {
        $kullanici = Auth::user();
        if (! $kullanici instanceof User) {
            return false;
        }

        if (KullaniciRolYardimcisi::superAdminVeyaIsAdmin($kullanici)) {
            return true;
        }

        $firmaId = app(TenantContextService::class)->aktifFirmaId();

        return app(SidebarService::class)->menuGorunurMu($kullanici, $firmaId, 'teklif_yonetimi', $yetkiKodu);
    }

    /**
     * @param  non-empty-array<int, string>  $yetkiKodlari
     */
    public static function herhangiBirTeklifYetkisiVarMi(array $yetkiKodlari): bool
    {
        foreach ($yetkiKodlari as $kod) {
            if (self::teklifYetkisiVarMi($kod)) {
                return true;
            }
        }

        return false;
    }
}
