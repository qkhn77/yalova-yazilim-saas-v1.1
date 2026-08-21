<?php

namespace App\Support;

use Illuminate\Http\Request;

class UygulamaUrl
{
    public static function uygulamaKoku(?Request $istek = null): string
    {
        $istek ??= request();

        $host = $istek instanceof Request
            ? self::istekHostuVeConfigSemasi($istek)
            : self::configHost();

        $yol = self::uygulamaYolu($istek);

        return rtrim($host.($yol !== '' ? '/'.$yol : ''), '/');
    }

    public static function uygulamaYolu(?Request $istek = null): string
    {
        $istek ??= request();

        if ($istek instanceof Request) {
            $kokYolu = parse_url($istek->root(), PHP_URL_PATH);
            if (is_string($kokYolu) && trim($kokYolu, '/') !== '') {
                return trim($kokYolu, '/');
            }

            $baseUrl = trim((string) $istek->getBaseUrl(), '/');
            if ($baseUrl !== '') {
                return $baseUrl;
            }
        }

        $configYolu = parse_url((string) config('app.url', ''), PHP_URL_PATH);

        return is_string($configYolu) ? trim($configYolu, '/') : '';
    }

    public static function rota(string $ad, array $parametreler = [], ?Request $istek = null): string
    {
        $goreliRota = route($ad, $parametreler, false);
        $host = $istek instanceof Request
            ? self::istekHostuVeConfigSemasi($istek)
            : self::configHost();
        $yol = self::uygulamaYolu($istek);

        if ($yol !== '') {
            $onEk = '/'.trim($yol, '/');

            if ($goreliRota === $onEk || str_starts_with($goreliRota, $onEk.'/')) {
                return rtrim($host, '/').$goreliRota;
            }
        }

        return rtrim(self::uygulamaKoku($istek), '/').$goreliRota;
    }

    private static function configHost(): string
    {
        $appUrl = (string) config('app.url', url('/'));
        $parcalar = parse_url($appUrl);

        if (! is_array($parcalar)) {
            return rtrim($appUrl, '/');
        }

        $sema = (string) ($parcalar['scheme'] ?? 'http');
        $host = (string) ($parcalar['host'] ?? 'localhost');
        $port = isset($parcalar['port']) ? ':'.$parcalar['port'] : '';

        return $sema.'://'.$host.$port;
    }

    private static function istekHostuVeConfigSemasi(Request $istek): string
    {
        $requestAuthority = trim((string) $istek->getHttpHost());
        if ($requestAuthority === '') {
            return self::configHost();
        }

        $configScheme = (string) (parse_url((string) config('app.url', ''), PHP_URL_SCHEME) ?: '');
        $scheme = $configScheme !== '' ? $configScheme : $istek->getScheme();

        return $scheme.'://'.$requestAuthority;
    }
}
