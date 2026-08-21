<?php

namespace App\Support;

use App\Models\Setting;

class RecaptchaAyarlari
{
    public static function etkinMi(): bool
    {
        return filter_var(Setting::get('recaptcha_enabled', false), FILTER_VALIDATE_BOOL)
            && static::siteKey() !== ''
            && static::secretKey() !== '';
    }

    public static function siteKey(): string
    {
        $key = Setting::get('recaptcha_site_key', '');

        return is_string($key) ? trim($key) : '';
    }

    public static function secretKey(): string
    {
        $secret = Setting::get('recaptcha_secret_key', '');

        return is_string($secret) ? trim($secret) : '';
    }
}
