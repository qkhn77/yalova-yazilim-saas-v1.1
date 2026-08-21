<?php

namespace App\Support;

use App\Services\FirmaAyarDeposu;
use App\Services\TenantContextService;

final class TablePaginationDefaults
{
    /**
     * ADMIN DATATABLE MİMARİ UYARISI
     *
     * Ortak tablo geliştirmesi yapmadan önce:
     * docs/architecture/admin-table-standard.md
     *
     * Normal sayfa açılışına ek sorgu, N+1, tüm kayıtları istemciye yükleme
     * veya tablo başına yinelenen JavaScript eklenemez.
     */
    public const SETTING_KEY = 'default_table_page_size';

    /** @var array<int, int|string> */
    public const OPTIONS = [10, 20, 50, 100, 1000, 'all'];

    public static function forActiveTenant(): int|string
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();

        if (! $firmaId) {
            return 10;
        }

        return self::normalize(
            app(FirmaAyarDeposu::class)->oku($firmaId, self::SETTING_KEY, 10),
        );
    }

    public static function normalize(mixed $value): int|string
    {
        if ($value === 'all') {
            return 'all';
        }

        $value = filter_var($value, FILTER_VALIDATE_INT);

        return in_array($value, [10, 20, 50, 100, 1000], true) ? $value : 10;
    }
}
