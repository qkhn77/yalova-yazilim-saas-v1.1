<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// PHP'nin yerel geliştirme sunucusunda mevcut CSS, JS, font ve görselleri
// Laravel'e göndermeden doğrudan servis et. Apache/canlı ortam bu bloktan etkilenmez.
if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    $requestPath = rawurldecode($requestPath);
    $requestPath = str_replace('\\', '/', $requestPath);
    if (! str_contains($requestPath, "\0") && ! str_contains($requestPath, '../')) {
        $requestedFile = __DIR__.'/'.ltrim($requestPath, '/');

        if (is_file($requestedFile) && strtolower((string) pathinfo($requestedFile, PATHINFO_EXTENSION)) !== 'php') {
            return false;
        }
    }
}

// Apache document rootu doğrudan public_html olduğunda bazı XAMPP
// kurulumlarında APP_KEY web sunucusu ortamına aktarılmayabilir. Laravel
// başlamadan önce proje .env değerlerini güvenli biçimde yükle.
if (! function_exists('yalovaPublicEnvDegeriBosMu')) {
    function yalovaPublicEnvDegeriBosMu(string $anahtar): bool
    {
        foreach ([getenv($anahtar), $_ENV[$anahtar] ?? null, $_SERVER[$anahtar] ?? null] as $deger) {
            if (is_string($deger) && trim($deger) !== '') {
                return false;
            }
        }

        return true;
    }
}

if (! function_exists('yalovaPublicEnvOku')) {
    function yalovaPublicEnvOku(string $dosyaYolu): array
    {
        if (! is_file($dosyaYolu) || ! is_readable($dosyaYolu)) {
            return [];
        }

        $sonuc = [];
        foreach (file($dosyaYolu, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $satir) {
            $satir = trim((string) $satir);
            if ($satir === '' || str_starts_with($satir, '#') || ! str_contains($satir, '=')) {
                continue;
            }

            [$anahtar, $deger] = explode('=', $satir, 2);
            $sonuc[trim($anahtar)] = trim($deger, " \t\n\r\0\x0B\"'");
        }

        return $sonuc;
    }
}

if (yalovaPublicEnvDegeriBosMu('APP_KEY')) {
    foreach ([__DIR__.'/../laravel-core/.env', __DIR__.'/../laravel-core/env', __DIR__.'/../.env', __DIR__.'/../env'] as $envDosyasi) {
        foreach (yalovaPublicEnvOku($envDosyasi) as $anahtar => $deger) {
            if (in_array($anahtar, ['APP_KEY', 'APP_ENV', 'APP_DEBUG', 'APP_URL'], true) && yalovaPublicEnvDegeriBosMu($anahtar)) {
                putenv($anahtar.'='.$deger);
                $_ENV[$anahtar] = $deger;
                $_SERVER[$anahtar] = $deger;
            }
        }

        if (! yalovaPublicEnvDegeriBosMu('APP_KEY')) {
            break;
        }
    }
}

// Bakım modu
if (file_exists($maintenance = __DIR__.'/../laravel-core/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Composer autoload
require __DIR__.'/../laravel-core/vendor/autoload.php';

// Local XAMPP alt klasöründe (/yalova-kamera) çalışırken Laravel'e kökten
// başlayan route yolu ver; canlıda public_html document root olduğunda URI / olur.
if (isset($_SERVER['REQUEST_URI'])) {
    $requestUri = parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
    $projectBasePath = '/' . trim(basename(dirname(__DIR__)), '/');

    if ($projectBasePath !== '/' && ($requestUri === $projectBasePath || str_starts_with($requestUri, $projectBasePath . '/'))) {
        $originalRequestUri = (string) $_SERVER['REQUEST_URI'];
        $normalizedPath = substr($requestUri, strlen($projectBasePath));
        $normalizedPath = $normalizedPath === '' ? '/' : $normalizedPath;
        $queryString = (string) ($_SERVER['QUERY_STRING'] ?? '');

        $_SERVER['REQUEST_URI'] = $normalizedPath . ($queryString !== '' ? '?' . $queryString : '');
        $_SERVER['ORIG_REQUEST_URI'] = $originalRequestUri;
    }
}

// Uygulama
(require_once __DIR__.'/../laravel-core/bootstrap/app.php')
    ->handleRequest(Request::capture());
