<?php

namespace App\Muhasebe\Servisler;

use Illuminate\Database\Eloquent\Builder;

/**
 * Sistem ve legacy ölçü birimi kodlarını tek bir domain anlamına bağlar.
 *
 * AD canonical koddur; ADET yalnızca eski kurulumlarda görülebilen alias'tır.
 */
final class BirimKodResolver
{
    public const ADET_CANONICAL = 'AD';

    public static function normalize(?string $kod): ?string
    {
        $kod = strtoupper(trim((string) $kod));

        if ($kod === '') {
            return null;
        }

        return $kod === 'ADET' ? self::ADET_CANONICAL : $kod;
    }

    /**
     * @return list<string>
     */
    public static function acceptedCodes(?string $kod): array
    {
        $canonical = self::normalize($kod);

        if ($canonical === null) {
            return [];
        }

        return $canonical === self::ADET_CANONICAL
            ? [self::ADET_CANONICAL, 'ADET']
            : [$canonical];
    }

    /**
     * Apply canonical/legacy matching to a Birim-style code column.
     *
     * @param  Builder<*>  $query
     * @return Builder<*>
     */
    public static function whereCode(Builder $query, string $column, ?string $kod): Builder
    {
        $codes = self::acceptedCodes($kod);

        if ($codes === []) {
            return $query->whereRaw('1 = 0');
        }

        $placeholders = implode(', ', array_fill(0, count($codes), '?'));

        return $query->whereRaw('UPPER(TRIM('.$column.')) IN ('.$placeholders.')', $codes);
    }
}
