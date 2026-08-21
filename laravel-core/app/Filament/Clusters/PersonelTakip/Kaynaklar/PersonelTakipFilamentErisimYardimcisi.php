<?php

namespace App\Filament\Clusters\PersonelTakip\Kaynaklar;

use App\Models\User;
use App\Services\SidebarService;
use App\Services\TenantContextService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class PersonelTakipFilamentErisimYardimcisi
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

    public static function personelYetkisiVarMi(string $yetkiKodu): bool
    {
        $kullanici = Auth::user();
        $firmaId = app(TenantContextService::class)->aktifFirmaId();

        return app(SidebarService::class)->menuGorunurMu(
            $kullanici instanceof User ? $kullanici : null,
            $firmaId,
            'personel_takip',
            $yetkiKodu
        );
    }

    /**
     * @param  list<string>  $yetkiKodlari
     */
    public static function herhangiBirPersonelErisimiVarMi(array $yetkiKodlari): bool
    {
        foreach ($yetkiKodlari as $kod) {
            if (self::personelYetkisiVarMi($kod)) {
                return true;
            }
        }

        return false;
    }
}
