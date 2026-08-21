<?php

namespace App\Filament\Clusters\Restoran\Kaynaklar;

use App\Models\User;
use App\Services\SidebarService;
use App\Services\TenantContextService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class RestoranFilamentErisimYardimcisi
{
    public static function kayitAktifFirmayaAitMi(Model $record): bool
    {
        $kullanici = Auth::user();
        if ($kullanici instanceof User && ((bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false))) {
            return true;
        }

        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        if (! $firmaId) {
            return false;
        }

        if (! array_key_exists('firma_id', $record->getAttributes())) {
            return true;
        }

        return (int) $record->getAttribute('firma_id') === (int) $firmaId;
    }

    public static function restoranYetkisiVarMi(string $yetkiKodu): bool
    {
        $kullanici = Auth::user();
        $firmaId = app(TenantContextService::class)->aktifFirmaId();

        return app(SidebarService::class)->menuGorunurMu(
            $kullanici instanceof User ? $kullanici : null,
            $firmaId,
            'restoran',
            $yetkiKodu
        );
    }

    /**
     * @param  list<string>  $yetkiKodlari
     */
    public static function herhangiBirRestoranErisimiVarMi(array $yetkiKodlari): bool
    {
        foreach ($yetkiKodlari as $kod) {
            if (self::restoranYetkisiVarMi($kod)) {
                return true;
            }
        }

        return false;
    }
}
