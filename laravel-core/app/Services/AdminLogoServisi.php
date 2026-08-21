<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Yönetim panelinde kullanılacak tek logo kaynağını çözer.
 * Aktif firmanın logosu varsa firma logosu, yoksa sistem logosu kullanılır.
 */
class AdminLogoServisi
{
    private const VARSAYILAN_YOL = 'teknik-servis-sablon-logolari/iV8V8hfdaQcYmGA4aF6QcjPweytbx7vZtweJFqso.png';

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
        return asset('storage/'.$this->yol($firmaId));
    }
}
