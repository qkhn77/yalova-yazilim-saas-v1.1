<?php

namespace App\Support;

use App\Models\Setting;

class RecaptchaAyarlari
{
    public static function etkinMi(): bool
    {
        if (env('RECAPTCHA_ENABLED') !== null) {
            return filter_var(env('RECAPTCHA_ENABLED'), FILTER_VALIDATE_BOOL)
                && static::siteKey() !== ''
                && static::secretKey() !== '';
        }

        return filter_var(Setting::get('recaptcha_enabled', false), FILTER_VALIDATE_BOOL)
            && static::siteKey() !== ''
            && static::secretKey() !== '';
    }

    public static function siteKey(): string
    {
        $key = env('RECAPTCHA_SITE_KEY');
        if ($key === null) {
            $key = Setting::get('recaptcha_site_key', '');
        }

        return is_string($key) ? trim($key) : '';
    }

    public static function secretKey(): string
    {
        $secret = env('RECAPTCHA_SECRET_KEY');
        if ($secret === null) {
            $secret = Setting::get('recaptcha_secret_key', '');
        }

        return is_string($secret) ? trim($secret) : '';
    }
}
