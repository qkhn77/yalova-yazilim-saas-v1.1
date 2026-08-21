<?php

namespace App\Filament\Clusters\TeknikServis\Kaynaklar;

use App\Models\User;
use App\Services\SidebarService;
use App\Services\TenantContextService;
use App\Support\KullaniciRolYardimcisi;
use App\Support\TeknikServisYetkiSablonlari;
use Illuminate\Support\Facades\Auth;

/**
 * Teknik Servis Filament ekranları için ortak erişim kontrolü (modül aboneliği + yetki).
 */
final class TeknikServisFilamentErisimYardimcisi
{
    public static function teknikServisModuluGoruntulenebilirMi(): bool
    {
        return static::teknikServisYetkisiVarMi(TeknikServisYetkiSablonlari::GORUNTULE);
    }

    public static function teknikServisYetkisiVarMi(string $yetkiKodu): bool
    {
        $kullanici = Auth::user();
        if (! $kullanici instanceof User) {
            return false;
        }

        if (KullaniciRolYardimcisi::superAdminVeyaIsAdmin($kullanici)) {
            return true;
        }

        $fid = app(TenantContextService::class)->aktifFirmaId();

        return app(SidebarService::class)->menuGorunurMu($kullanici, $fid, 'teknik_servis', $yetkiKodu);
    }

    /**
     * @param  list<string>  $yetkiKodlari
     */
    public static function herhangiBirTeknikServisErisimiVarMi(array $yetkiKodlari): bool
    {
        foreach ($yetkiKodlari as $kod) {
            if (static::teknikServisYetkisiVarMi($kod)) {
                return true;
            }
        }

        return false;
    }
}
