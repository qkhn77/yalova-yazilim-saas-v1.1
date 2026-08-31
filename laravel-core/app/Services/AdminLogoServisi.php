<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Yönetim panelinde kullanılacak tek logo kaynağını çözer.
 * Aktif firmanın logosu varsa firma logosu, yoksa sistem logosu kullanılır.
 */
class AdminLogoServisi
{
    /** Projede kullanılan güncel Yalova Kamera logosu. */
    private const VARSAYILAN_YOL = 'images/logo/yalova-yazilim.svg';

    public function yol(?int $firmaId = null): string
    {
        $firmaId ??= app(TenantContextService::class)->aktifFirmaId();

        if ($firmaId) {
            $firmaLogosu = app(FirmaAyarDeposu::class)->oku($firmaId, 'logo');
            if (is_string($firmaLogosu) && trim($firmaLogosu) !== '') {
                return ltrim($firmaLogosu, '/');
            }
        }

        $sistemLogosu = Setting::get('sistem_admin_logo');

        return is_string($sistemLogosu) && trim($sistemLogosu) !== ''
            ? ltrim($sistemLogosu, '/')
            : self::VARSAYILAN_YOL;
    }

    public function url(?int $firmaId = null): string
    {
        $yol = $this->yol($firmaId);

        if (str_starts_with($yol, 'public/')) {
            return asset(ltrim(substr($yol, strlen('public/')), '/'));
        }

        // public_html altındaki statik marka varlıkları storage üzerinden değil,
        // doğrudan web kökünden servis edilir.
        if (str_starts_with($yol, 'images/')) {
            return asset($yol);
        }

        return asset('storage/'.$yol);
    }
}
