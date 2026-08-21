<?php

declare(strict_types=1);

const DIAGNOSE_KEY = '312c1eb9e96151e70f3ce4847b8a4b480ed2e337a6f62afa62bac8e6d73bc90f';

header('Content-Type: text/plain; charset=utf-8');

if (! hash_equals(DIAGNOSE_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(404);
    echo "Bulunamadi.\n";
    exit;
}

ini_set('display_errors', '0');
set_time_limit(120);

function line(string $key, mixed $value): void
{
    if (is_bool($value)) {
        $value = $value ? 'evet' : 'hayir';
    }

    echo str_pad($key, 30).": ".$value."\n";
}

function maskValue(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    if (strlen($value) <= 4) {
        return '****';
    }

    return substr($value, 0, 2).str_repeat('*', max(4, strlen($value) - 4)).substr($value, -2);
}

function readEnvFile(string $path): array
{
    if (! is_file($path) || ! is_readable($path)) {
        return [];
    }

    $rows = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (! is_array($rows)) {
        return [];
    }

    $env = [];
    foreach ($rows as $row) {
        $row = trim(ltrim((string) $row, "\xEF\xBB\xBF"));
        if ($row === '' || str_starts_with($row, '#') || ! str_contains($row, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $row, 2);
        $key = trim($key);
        $value = trim($value);
        if (strlen($value) >= 2 && (($value[0] === '"' && $value[strlen($value) - 1] === '"') || ($value[0] === "'" && $value[strlen($value) - 1] === "'"))) {
            $value = substr($value, 1, -1);
        }

        $env[$key] = $value;
    }

    return $env;
}

$basePath = __DIR__;
if (! is_file($basePath.'/artisan') && is_file(dirname(__DIR__).'/artisan')) {
    $basePath = dirname(__DIR__);
}

echo "Yalova Kamera canli tani\n";
echo "Tarih: ".date('Y-m-d H:i:s')."\n\n";

line('PHP version', PHP_VERSION);
line('Base path', $basePath);
line('artisan var', is_file($basePath.'/artisan'));
line('vendor/autoload var', is_file($basePath.'/vendor/autoload.php'));
line('.env var', is_file($basePath.'/.env'));
line('storage writable', is_writable($basePath.'/storage'));
line('bootstrap/cache writable', is_writable($basePath.'/bootstrap/cache'));

$env = readEnvFile($basePath.'/.env');
echo "\nENV ozeti\n";
line('APP_ENV', $env['APP_ENV'] ?? '');
line('APP_URL', $env['APP_URL'] ?? '');
line('APP_DEBUG', $env['APP_DEBUG'] ?? '');
line('DB_CONNECTION', $env['DB_CONNECTION'] ?? '');
line('DB_HOST', $env['DB_HOST'] ?? '');
line('DB_DATABASE', $env['DB_DATABASE'] ?? '');
line('DB_USERNAME', $env['DB_USERNAME'] ?? '');
line('DB_PASSWORD', maskValue($env['DB_PASSWORD'] ?? ''));

try {
    if (! is_file($basePath.'/vendor/autoload.php') || ! is_file($basePath.'/bootstrap/app.php')) {
        throw new RuntimeException('Laravel dosyalari eksik.');
    }

    chdir($basePath);
    require $basePath.'/vendor/autoload.php';
    $app = require $basePath.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    echo "\nLaravel\n";
    line('boot', 'OK');
    line('config app.env', (string) config('app.env'));
    line('config app.url', (string) config('app.url'));
    line('cache default', (string) config('cache.default'));

    try {
        $row = Illuminate\Support\Facades\DB::selectOne('select 1 as ok');
        line('database select 1', isset($row->ok) ? 'OK' : 'Bilinmiyor');
        line('database name', Illuminate\Support\Facades\DB::getDatabaseName());
        line('users count', Illuminate\Support\Facades\Schema::hasTable('users') ? Illuminate\Support\Facades\DB::table('users')->count() : 'users tablosu yok');
    } catch (Throwable $e) {
        line('database hata', $e->getMessage());
    }

    try {
        $status = $kernel->call('migrate:status');
        line('migrate:status', $status === 0 ? 'OK' : 'HATA '.$status);
    } catch (Throwable $e) {
        line('migrate hata', $e->getMessage());
    }
} catch (Throwable $e) {
    echo "\nLaravel boot HATA\n";
    line('mesaj', $e->getMessage());
    line('dosya', $e->getFile().':'.$e->getLine());
}

$logFiles = glob($basePath.'/storage/logs/*.log') ?: [];
rsort($logFiles);

echo "\nSon log ozeti\n";
if ($logFiles === []) {
    echo "Log dosyasi yok.\n";
} else {
    $latest = $logFiles[0];
    line('log dosyasi', basename($latest));
    $content = @file($latest, FILE_IGNORE_NEW_LINES);
    if (is_array($content)) {
        $tail = array_slice($content, -80);
        foreach ($tail as $row) {
            $row = preg_replace('/(password|secret|token|key)(=|:|\\s+)[^\\s,]+/i', '$1$2***', (string) $row);
            echo $row."\n";
        }
    }
}

echo "\nIs bitince diagnose.php dosyasini FTP'den silin.\n";
