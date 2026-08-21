<?php

namespace App\Filament\Clusters\MasrafTakip\Kaynaklar;

use App\Models\User;
use App\Services\SidebarService;
use App\Services\TenantContextService;
use App\Support\KullaniciRolYardimcisi;
use Illuminate\Support\Facades\Auth;

final class MasrafTakipFilamentErisimYardimcisi
{
    public static function masrafTakipYetkisiVarMi(string $yetkiKodu): bool
    {
        $kullanici = Auth::user();
        if (! $kullanici instanceof User) {
            return false;
        }

        if (KullaniciRolYardimcisi::superAdminVeyaIsAdmin($kullanici)) {
            return true;
        }

        $firmaId = app(TenantContextService::class)->aktifFirmaId();

        return app(SidebarService::class)->menuGorunurMu($kullanici, $firmaId, 'masraf_takip', $yetkiKodu);
    }

    /** @param non-empty-array<int, string> $yetkiKodlari */
    public static function herhangiBirMasrafTakipYetkisiVarMi(array $yetkiKodlari): bool
    {
        foreach ($yetkiKodlari as $yetkiKodu) {
            if (self::masrafTakipYetkisiVarMi($yetkiKodu)) {
                return true;
            }
        }

        return false;
    }
}
