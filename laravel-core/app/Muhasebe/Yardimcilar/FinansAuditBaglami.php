<?php

namespace App\Muhasebe\Yardimcilar;

use Illuminate\Support\Facades\Auth;

/**
 * Finans hareketleri için denetim (audit) alanları — panel / konsol / IP / kullanıcı.
 */
class FinansAuditBaglami
{
    /**
     * @return array<string, mixed>
     */
    public static function otomatikFinansAlanlari(): array
    {
        return [
            'islem_yapan_kullanici_id' => Auth::id(),
            'islem_kaynagi' => app()->runningInConsole() ? 'konsol' : 'panel',
            'audit_ip' => self::istemciIp(),
        ];
    }

    public static function istemciIp(): ?string
    {
        try {
            return request()?->ip();
        } catch (\Throwable) {
            return null;
        }
    }
}
