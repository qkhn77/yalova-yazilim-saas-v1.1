<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    private const ALL_SETTINGS_CACHE_KEY = 'settings.all';
    private static ?array $runtimeSettings = null;

    protected $fillable = ['key', 'value', 'group'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = static::allCached();

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public static function getMany(array $defaults = []): array
    {
        $settings = static::allCached();
        $values = [];

        foreach ($defaults as $key => $default) {
            $values[$key] = array_key_exists($key, $settings) ? $settings[$key] : $default;
        }

        return $values;
    }

    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        static::clearCache($key);
    }

    public static function clearCache(?string $key = null): void
    {
        self::$runtimeSettings = null;
        Cache::forget(self::ALL_SETTINGS_CACHE_KEY);

        if ($key !== null) {
            Cache::forget("setting.{$key}");
        }
    }

    protected static function allCached(): array
    {
        if (self::$runtimeSettings !== null) {
            return self::$runtimeSettings;
        }

        self::$runtimeSettings = Cache::remember(self::ALL_SETTINGS_CACHE_KEY, 3600, static function (): array {
            return static::query()
                ->pluck('value', 'key')
                ->all();
        });

        return self::$runtimeSettings;
    }
}
