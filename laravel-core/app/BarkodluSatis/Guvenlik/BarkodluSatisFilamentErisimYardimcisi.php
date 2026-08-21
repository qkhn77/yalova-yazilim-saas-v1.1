<?php

namespace App\BarkodluSatis\Guvenlik;

use App\Models\User;
use App\Services\SidebarService;
use App\Services\TenantContextService;
use App\Support\KullaniciRolYardimcisi;
use Illuminate\Support\Facades\Auth;

final class BarkodluSatisFilamentErisimYardimcisi
{
    public static function barkodluSatisYetkisiVarMi(string $yetkiKodu): bool
    {
        $kullanici = Auth::user();
        if (! $kullanici instanceof User) {
            return false;
        }

        $firmaId = self::aktifFirmaId();

        if (self::yoneticiMi($kullanici)) {
            return true;
        }

        return app(SidebarService::class)->menuGorunurMu($kullanici, $firmaId, 'barkodlu_satis', $yetkiKodu);
    }

    /**
     * @param  array<int, string>  $yetkiKodlari
     */
    public static function herhangiBirBarkodluSatisYetkisiVarMi(array $yetkiKodlari): bool
    {
        foreach ($yetkiKodlari as $kod) {
            if (self::barkodluSatisYetkisiVarMi($kod)) {
                return true;
            }
        }

        return false;
    }

    private static function aktifFirmaId(): ?int
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();

        return $firmaId ? (int) $firmaId : null;
    }

    private static function yoneticiMi(User $kullanici): bool
    {
        return KullaniciRolYardimcisi::superAdminVeyaIsAdmin($kullanici);
    }
}
