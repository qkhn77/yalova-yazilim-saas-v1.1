<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;

final class AdminLayoutPreference
{
    public const MODERN_VERTICAL = 'modern-vertical';

    public const COMPACT_VERTICAL = 'compact-vertical';

    public const HORIZONTAL = 'horizontal';

    public const DEFAULT = self::MODERN_VERTICAL;

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::MODERN_VERTICAL => 'Modern Vertical',
            self::COMPACT_VERTICAL => 'Compact Vertical',
            self::HORIZONTAL => 'Horizontal',
        ];
    }

    public static function normalize(mixed $layout): string
    {
        $layout = is_string($layout) ? trim($layout) : '';

        return array_key_exists($layout, self::options())
            ? $layout
            : self::DEFAULT;
    }

    public static function forUser(?Authenticatable $user): string
    {
        return self::normalize($user?->getAuthIdentifier() ? $user->getAttribute('admin_layout') : null);
    }

    public static function bodyClass(?Authenticatable $user): string
    {
        return 'saas-layout-'.self::forUser($user);
    }
}
