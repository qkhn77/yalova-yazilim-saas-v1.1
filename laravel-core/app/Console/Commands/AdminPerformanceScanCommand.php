<?php

namespace App\Console\Commands;

use App\Models\Firma;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use App\Services\TenantContextService;
use Illuminate\Console\Command;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class AdminPerformanceScanCommand extends Command
{
    protected $signature = 'admin:performance-scan
        {--url= : APP_URL yerine kullanilacak taban URL}
        {--runs=3 : Her sayfa icin tekrar sayisi}
        {--only= : Sadece URI icinde bu metni gecen sayfalar}
        {--probe : Lokal ortamda app/query surelerini HTTP header olarak olcer}
        {--timeout=30 : Her HTTP istegi icin saniye cinsinden timeout}
        {--cleanup-temp-user : Gecici performans kullanicisini tarama sonunda sil}';

    protected $description = 'Filament admin GET sayfalarini HTTP uzerinden olcer ve yavas sayfalari siralar.';

    public function handle(): int
    {
        $baseUrl = $this->tabanUrl();
        $adminPath = trim(AdminPanelProvider::adminPath(), '/');
        $runs = max(1, (int) $this->option('runs'));
        $timeout = max(3, (int) $this->option('timeout'));
        $only = trim((string) ($this->option('only') ?: ''));
        $probe = (bool) $this->option('probe');

        $firma = Firma::query()->orderBy('id')->first();
        if (! $firma) {
            $this->error('Olcum icin firma bulunamadi.');

            return self::FAILURE;
        }

        $kullanici = $this->geciciAdminHazirla();
        $cookie = $this->oturumCookieOlustur($kullanici, $firma);
        $routes = $this->adminGetRoutes($adminPath, $only);

        $this->line(sprintf('Dogrudan olculecek admin sayfasi: %d', count($routes)));
        $this->line(sprintf('Runs: %d', $runs));

        $satirlar = [];
        $index = 0;

        foreach ($routes as $route) {
            $index++;
            $url = $baseUrl.'/'.ltrim($route['uri'], '/');
            $durations = [];
            $status = null;
            $bytes = 0;
            $error = '';
            $finalUrl = $url;
            $probeAppMs = null;
            $probeQueries = null;
            $probeQueryMs = null;
            $probeSlowest = '';
            $probeGzipMs = null;
            $probeGzipRawBytes = null;
            $probeGzipBytes = null;

            for ($run = 1; $run <= $runs; $run++) {
                $baslangic = hrtime(true);
                $effectiveUrl = $url;
                $headers = [
                    'Accept' => 'text/html',
                    'Cookie' => $cookie,
                ];

                if ($probe) {
                    $headers['X-Admin-Performance-Probe'] = '1';
                    $headers['Accept-Encoding'] = 'gzip';
                }

                try {
                    $yanit = Http::timeout($timeout)
                        ->withHeaders($headers)
                        ->withOptions([
                            'allow_redirects' => ['max' => 5],
                            'on_stats' => function ($stats) use (&$effectiveUrl): void {
                                $effectiveUrl = (string) $stats->getEffectiveUri();
                            },
                        ])
                        ->get($url);

                    $durations[] = (hrtime(true) - $baslangic) / 1_000_000;
                    $status = $yanit->status();
                    $bytes = strlen($yanit->body());
                    $finalUrl = $effectiveUrl;
                    if ($probe) {
                        $probeAppMs = $yanit->header('X-Admin-Perf-App-Ms');
                        $probeQueries = $yanit->header('X-Admin-Perf-Queries');
                        $probeQueryMs = $yanit->header('X-Admin-Perf-Query-Ms');
                        $probeSlowest = $this->probeSlowestHeader($yanit->header('X-Admin-Perf-Slowest'));
                        $probeGzipMs = $yanit->header('X-Admin-Gzip-Ms');
                        $probeGzipRawBytes = $yanit->header('X-Admin-Gzip-Raw-Bytes');
                        $probeGzipBytes = $yanit->header('X-Admin-Gzip-Bytes');
                    }
                } catch (\Throwable $e) {
                    $durations[] = (hrtime(true) - $baslangic) / 1_000_000;
                    $status = 'hata';
                    $error = $e->getMessage();
                }
            }

            $avg = array_sum($durations) / max(1, count($durations));
            $row = [
                'rank' => null,
                'module' => $this->moduleName($route['uri'], $adminPath),
                'uri' => $route['uri'],
                'name' => $route['name'],
                'action' => $route['action'],
                'status' => $status,
                'avg_ms' => round($avg, 2),
                'min_ms' => round(min($durations), 2),
                'max_ms' => round(max($durations), 2),
                'runs_ms' => implode('|', array_map(fn (float $duration): string => (string) round($duration, 2), $durations)),
                'bytes' => $bytes,
                'app_ms' => $probeAppMs,
                'queries' => $probeQueries,
                'query_ms' => $probeQueryMs,
                'slowest_queries' => $probeSlowest,
                'gzip_ms' => $probeGzipMs,
                'gzip_raw_bytes' => $probeGzipRawBytes,
                'gzip_bytes' => $probeGzipBytes,
                'final_url' => $finalUrl,
                'error' => $error,
            ];

            $satirlar[] = $row;
            $this->line(sprintf(
                '[%d/%d] %s | %s | %.2f ms | HTTP %s',
                $index,
                count($routes),
                $row['module'],
                $route['uri'],
                $row['avg_ms'],
                (string) $status
            ));
        }

        usort($satirlar, fn (array $a, array $b): int => $b['avg_ms'] <=> $a['avg_ms']);
        foreach ($satirlar as $i => &$satir) {
            $satir['rank'] = $i + 1;
        }
        unset($satir);

        $csvPath = base_path('tools/admin-artisan-performance-results-latest.csv');
        $this->csvYaz($csvPath, $satirlar);

        $this->line('');
        $this->line('En yavas ilk 20 sayfa:');
        foreach (array_slice($satirlar, 0, 20) as $satir) {
            $this->line(sprintf(
                '%d. %s | %s | %.2f ms | HTTP %s',
                $satir['rank'],
                $satir['module'],
                $satir['uri'],
                $satir['avg_ms'],
                (string) $satir['status']
            ));
        }

        $ustLimit = array_values(array_filter($satirlar, fn (array $satir): bool => (float) $satir['avg_ms'] > 500 && (int) $satir['status'] === 200));
        $this->line(sprintf('500ms ustu HTTP 200 sayfa: %d', count($ustLimit)));
        $this->line('CSV: '.$csvPath);

        if ((bool) $this->option('cleanup-temp-user')) {
            $kullanici->forceDelete();
            $this->line('Gecici performans admin kullanicisi temizlendi.');
        }

        return self::SUCCESS;
    }

    private function geciciAdminHazirla(): User
    {
        $email = 'perf-admin@local.test';
        $user = User::withoutGlobalScopes()
            ->withTrashed()
            ->where('email', $email)
            ->first() ?? new User();

        $user->email = $email;
        $user->name = 'Performance Admin';
        $user->ad_soyad = 'Performance Admin';
        $user->kullanici_adi = 'perf_admin_measure';
        $user->password = Hash::make(Str::random(64));
        $user->super_admin_mi = true;

        if (method_exists($user, 'trashed') && $user->trashed()) {
            $user->restore();
        }

        $user->save();

        return $user;
    }

    private function oturumCookieOlustur(User $kullanici, Firma $firma): string
    {
        $request = Request::create('/', 'GET');
        $session = app('session')->driver();
        $session->setId(Str::random(40));
        $session->start();
        $request->setLaravelSession($session);

        app()->instance('request', $request);
        app('url')->setRequest($request);

        Auth::login($kullanici);
        $request->setUserResolver(fn (): User => $kullanici);
        app(TenantContextService::class)->firmaAyarla($firma);

        $session->save();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $cookieAdi = (string) config('session.cookie');
        $cookieDegeri = app('encrypter')->encrypt(
            CookieValuePrefix::create($cookieAdi, app('encrypter')->getKey()).$session->getId(),
            false
        );

        return $cookieAdi.'='.$cookieDegeri;
    }

    /**
     * @return array<int, array{uri:string,name:string,action:string}>
     */
    private function adminGetRoutes(string $adminPath, string $only): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            $uri = trim($route->uri(), '/');
            $name = (string) ($route->getName() ?: '');

            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if ($uri !== $adminPath && ! str_starts_with($uri, $adminPath.'/')) {
                continue;
            }

            if ($uri === $adminPath.'/logout' || str_contains($uri, '{')) {
                continue;
            }

            if ($only !== '' && ! str_contains($uri, $only)) {
                continue;
            }

            if (! str_starts_with($name, 'filament.admin.') && ! str_starts_with($name, 'admin.')) {
                continue;
            }

            $routes[] = [
                'uri' => $uri,
                'name' => $name,
                'action' => ltrim($route->getActionName(), '\\'),
            ];
        }

        usort($routes, fn (array $a, array $b): int => $a['uri'] <=> $b['uri']);

        return $routes;
    }

    private function csvYaz(string $path, array $satirlar): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('CSV yazilamadi: '.$path);
        }

        $headers = ['rank', 'module', 'uri', 'name', 'action', 'status', 'avg_ms', 'min_ms', 'max_ms', 'runs_ms', 'bytes', 'app_ms', 'queries', 'query_ms', 'gzip_ms', 'gzip_raw_bytes', 'gzip_bytes', 'slowest_queries', 'final_url', 'error'];
        fputcsv($handle, $headers);

        foreach ($satirlar as $satir) {
            fputcsv($handle, array_map(fn (string $header): mixed => $satir[$header] ?? '', $headers));
        }

        fclose($handle);
    }

    private function moduleName(string $uri, string $adminPath): string
    {
        $parts = explode('/', trim($uri, '/'));

        return $parts[1] ?? $adminPath;
    }

    private function tabanUrl(): string
    {
        $url = trim((string) ($this->option('url') ?: config('app.url', 'http://localhost')));

        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = 'http://'.$url;
        }

        return rtrim($url, '/');
    }

    private function probeSlowestHeader(?string $encoded): string
    {
        if (! is_string($encoded) || $encoded === '') {
            return '';
        }

        $decoded = base64_decode($encoded, true);
        if (! is_string($decoded) || $decoded === '') {
            return '';
        }

        $queries = json_decode($decoded, true);
        if (! is_array($queries)) {
            return '';
        }

        return collect($queries)
            ->map(fn (array $query): string => sprintf(
                '%sms %s',
                (string) ($query['ms'] ?? ''),
                (string) ($query['sql'] ?? '')
            ))
            ->implode(' || ');
    }
}
