<?php

namespace App\Console\Commands;

use App\Models\Firma;
use App\Models\User;
use App\Services\TenantContextService;
use Illuminate\Console\Command;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use ReflectionMethod;
use Symfony\Component\Process\Process;
use Throwable;

class AdminParameterPerformanceScanCommand extends Command
{
    protected $signature = 'admin:parameter-performance-scan
        {--url= : APP_URL yerine kullanilacak taban URL}
        {--runs=3 : Her sayfa icin tekrar sayisi}
        {--only= : Sadece URI veya URI sablonu icinde bu metni gecen sayfalar}
        {--probe : Lokal ortamda app/query surelerini HTTP header olarak olcer}
        {--use-fixtures : Eksik parametreli sayfalar icin gecici fixture kayitlari hazirla}
        {--keep-fixtures : Fixture kayitlarini tarama sonunda silme}
        {--timeout=30 : Her HTTP istegi icin saniye cinsinden timeout}
        {--cleanup-temp-user : Gecici performans kullanicisini tarama sonunda sil}';

    protected $description = 'Parametreli Filament admin GET sayfalarini ornek kayitlarla HTTP uzerinden olcer.';

    public function handle(): int
    {
        $baseUrl = $this->tabanUrl();
            $runs = max(1, (int) $this->option('runs'));
            $timeout = max(3, (int) $this->option('timeout'));
            $only = trim((string) ($this->option('only') ?: ''));
            $probe = (bool) $this->option('probe');

        if ((bool) $this->option('use-fixtures')) {
            $this->fixtureScriptCalistir('ensure');
        }

        try {
            $firma = Firma::query()->orderBy('id')->first();
            if (! $firma) {
                $this->error('Olcum icin firma bulunamadi.');

                return self::FAILURE;
            }

            $kullanici = $this->geciciAdminHazirla();
            $this->oturumHazirla($kullanici, $firma);
            $cookie = $this->oturumCookieOlustur();
            $samples = $this->parametreliRouteOrnekleri($only);
            $measurable = array_values(array_filter($samples, fn (array $sample): bool => $sample['status'] === 'ok' && $sample['uri'] !== null));
            $skipped = array_values(array_filter($samples, fn (array $sample): bool => $sample['status'] !== 'ok' || $sample['uri'] === null));
            $skippedCsvPath = base_path('tools/admin-artisan-parameter-performance-skipped-latest.csv');
            $this->skippedCsvYaz($skippedCsvPath, $skipped);

        $this->line(sprintf('Parametreli admin route: %d', count($samples)));
        $this->line(sprintf('Olculecek parametreli sayfa: %d', count($measurable)));
        $this->line(sprintf('Olculemeyen parametreli route: %d', count($skipped)));
        $this->line(sprintf('Runs: %d', $runs));

        $satirlar = [];
        $index = 0;

        foreach ($measurable as $sample) {
            $index++;
            $url = $baseUrl.'/'.ltrim((string) $sample['uri'], '/');
            $durations = [];
            $status = null;
            $bytes = 0;
            $error = '';
            $finalUrl = $url;
            $probeAppMs = null;
            $probeQueries = null;
            $probeQueryMs = null;
            $probeSlowest = '';

            for ($run = 1; $run <= $runs; $run++) {
                $baslangic = hrtime(true);
                $effectiveUrl = $url;
                $headers = [
                    'Accept' => 'text/html',
                    'Cookie' => $cookie,
                ];

                if ($probe) {
                    $headers['X-Admin-Performance-Probe'] = '1';
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
                    }
                } catch (Throwable $e) {
                    $durations[] = (hrtime(true) - $baslangic) / 1_000_000;
                    $status = 'hata';
                    $error = $e->getMessage();
                }
            }

            $avg = array_sum($durations) / max(1, count($durations));
            $row = [
                'rank' => null,
                'module' => $this->moduleName((string) $sample['uri']),
                'uri' => (string) $sample['uri'],
                'uri_template' => (string) $sample['uri_template'],
                'name' => (string) $sample['name'],
                'action' => (string) $sample['action'],
                'model' => (string) ($sample['model'] ?? ''),
                'record_key' => (string) ($sample['record_key'] ?? ''),
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
                'final_url' => $finalUrl,
                'error' => $error,
            ];

            $satirlar[] = $row;
            $this->line(sprintf(
                '[%d/%d] %s | %s | %.2f ms | HTTP %s',
                $index,
                count($measurable),
                $row['module'],
                $row['uri'],
                $row['avg_ms'],
                (string) $status
            ));
        }

        usort($satirlar, fn (array $a, array $b): int => $b['avg_ms'] <=> $a['avg_ms']);
        foreach ($satirlar as $i => &$satir) {
            $satir['rank'] = $i + 1;
        }
        unset($satir);

        $csvPath = base_path('tools/admin-artisan-parameter-performance-results-latest.csv');
        $this->csvYaz($csvPath, $satirlar);

        $this->line('');
        $this->line('Parametreli en yavas ilk 20 sayfa:');
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
        $this->line(sprintf('500ms ustu HTTP 200 parametreli sayfa: %d', count($ustLimit)));
        $this->line('CSV: '.$csvPath);
        $this->line('Olculemeyen route CSV: '.$skippedCsvPath);

            if ((bool) $this->option('cleanup-temp-user')) {
                $kullanici->forceDelete();
                $this->line('Gecici performans admin kullanicisi temizlendi.');
            }

            return self::SUCCESS;
        } finally {
            if ((bool) $this->option('use-fixtures') && ! (bool) $this->option('keep-fixtures')) {
                $this->fixtureScriptCalistir('delete');
            }
        }
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

    private function fixtureScriptCalistir(string $action): void
    {
        $script = base_path('tools/admin_perf_fixture_records.php');
        $process = new Process([PHP_BINARY, $script, $action], base_path());
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        $this->line($action === 'ensure' ? 'Fixture kayitlari hazirlandi.' : 'Fixture kayitlari temizlendi.');
    }

    private function oturumHazirla(User $kullanici, Firma $firma): void
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
    }

    private function oturumCookieOlustur(): string
    {
        session()->save();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $cookieAdi = (string) config('session.cookie');
        $cookieDegeri = app('encrypter')->encrypt(
            CookieValuePrefix::create($cookieAdi, app('encrypter')->getKey()).session()->getId(),
            false
        );

        return $cookieAdi.'='.$cookieDegeri;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parametreliRouteOrnekleri(string $only): array
    {
        $samples = [];

        foreach (Route::getRoutes() as $route) {
            $uri = trim($route->uri(), '/');
            $name = $route->getName();

            if (
                ! in_array('GET', $route->methods(), true) ||
                ! str_starts_with($uri, 'admin') ||
                ! str_contains($uri, '{') ||
                ! is_string($name) ||
                ! str_starts_with($name, 'filament.admin.')
            ) {
                continue;
            }

            $action = $route->getActionName();
            $actionClass = str_contains($action, '@') ? strstr($action, '@', true) : $action;
            $resourceClass = is_string($actionClass) ? $this->staticMethod($actionClass, 'getResource') : null;
            $modelClass = is_string($resourceClass) ? $this->staticMethod($resourceClass, 'getModel') : null;
            $sample = is_string($modelClass)
                ? $this->firstRecordForRoute($resourceClass, $modelClass, $uri, $name)
                : ['record' => null, 'status' => 'no_model'];

            $record = $sample['record'];
            $resolvedUri = null;
            $recordKey = null;
            $status = $sample['status'];

            if ($record instanceof Model) {
                $recordKey = (string) $record->getRouteKey();
                $resolvedUri = preg_replace('/\{record[^}]*\}/', rawurlencode($recordKey), $uri);
                $status = is_string($resolvedUri) && ! str_contains($resolvedUri, '{') ? 'ok' : 'unresolved_parameter';
            }

            if ($only !== '' && ! str_contains($uri, $only) && (! is_string($resolvedUri) || ! str_contains($resolvedUri, $only))) {
                continue;
            }

            $samples[] = [
                'uri_template' => $uri,
                'uri' => $resolvedUri,
                'name' => $name,
                'action' => $action,
                'resource' => $resourceClass,
                'model' => $modelClass,
                'record_key' => $recordKey,
                'status' => $status,
            ];
        }

        usort($samples, fn (array $a, array $b): int => strcmp((string) $a['uri_template'], (string) $b['uri_template']));

        return $samples;
    }

    /**
     * @return array{record: Model|null, status: string}
     */
    private function firstRecordForRoute(?string $resourceClass, string $modelClass, string $uri, string $name): array
    {
        if (! is_subclass_of($modelClass, Model::class)) {
            return ['record' => null, 'status' => 'no_record'];
        }

        $model = new $modelClass();
        $keyName = $model->getKeyName();
        $authorizationMethod = $this->authorizationMethodForRoute($uri, $name);
        $hadUnauthorizedRecords = false;

        if (is_string($resourceClass) && class_exists($resourceClass)) {
            try {
                $query = $this->staticMethod($resourceClass, 'getEloquentQuery');

                if ($query instanceof Builder) {
                    $record = $this->firstAuthorizedRecordFromQuery($query, $resourceClass, $authorizationMethod);

                    if ($record instanceof Model) {
                        return ['record' => $record, 'status' => 'ok'];
                    }

                    $hadUnauthorizedRecords = $authorizationMethod !== null && (clone $query)->limit(1)->exists();
                }
            } catch (Throwable) {
            }
        }

        foreach ([
            fn () => $modelClass::query()->orderBy($keyName)->limit(50)->get(),
            fn () => $modelClass::withoutGlobalScopes()->orderBy($keyName)->limit(50)->get(),
        ] as $resolver) {
            try {
                foreach ($resolver() as $record) {
                    if ($record instanceof Model && $this->recordAuthorized($resourceClass, $record, $authorizationMethod)) {
                        return ['record' => $record, 'status' => 'ok'];
                    }

                    if ($record instanceof Model) {
                        $hadUnauthorizedRecords = $authorizationMethod !== null;
                    }
                }
            } catch (Throwable) {
            }
        }

        return ['record' => null, 'status' => $hadUnauthorizedRecords ? 'no_authorization' : 'no_record'];
    }

    private function firstAuthorizedRecordFromQuery(Builder $query, ?string $resourceClass, ?string $authorizationMethod): ?Model
    {
        $queryModel = $query->getModel();
        $records = (clone $query)
            ->orderBy($queryModel->qualifyColumn($queryModel->getKeyName()))
            ->limit(50)
            ->get();

        foreach ($records as $record) {
            if ($record instanceof Model && $this->recordAuthorized($resourceClass, $record, $authorizationMethod)) {
                return $record;
            }
        }

        return null;
    }

    private function recordAuthorized(?string $resourceClass, Model $record, ?string $method): bool
    {
        if (! is_string($resourceClass) || ! is_string($method) || ! method_exists($resourceClass, $method)) {
            return true;
        }

        try {
            return (bool) $this->staticMethod($resourceClass, $method, [$record]);
        } catch (Throwable) {
            return false;
        }
    }

    private function staticMethod(string $class, string $method, array $args = []): mixed
    {
        if (! class_exists($class) || ! method_exists($class, $method)) {
            return null;
        }

        $reflection = new ReflectionMethod($class, $method);
        if (! $reflection->isStatic()) {
            return null;
        }

        if (! $reflection->isPublic()) {
            $reflection->setAccessible(true);
        }

        return $reflection->invokeArgs(null, $args);
    }

    private function authorizationMethodForRoute(string $uri, string $name): ?string
    {
        if (str_ends_with($uri, '/edit') || str_ends_with($name, '.edit')) {
            return 'canEdit';
        }

        if (str_ends_with($name, '.view')) {
            return 'canView';
        }

        return null;
    }

    private function csvYaz(string $path, array $satirlar): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('CSV yazilamadi: '.$path);
        }

        $headers = ['rank', 'module', 'uri', 'uri_template', 'name', 'action', 'model', 'record_key', 'status', 'avg_ms', 'min_ms', 'max_ms', 'runs_ms', 'bytes', 'app_ms', 'queries', 'query_ms', 'slowest_queries', 'final_url', 'error'];
        fputcsv($handle, $headers);

        foreach ($satirlar as $satir) {
            fputcsv($handle, array_map(fn (string $header): mixed => $satir[$header] ?? '', $headers));
        }

        fclose($handle);
    }

    private function skippedCsvYaz(string $path, array $satirlar): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('CSV yazilamadi: '.$path);
        }

        $headers = ['uri_template', 'uri', 'name', 'action', 'resource', 'model', 'record_key', 'status'];
        fputcsv($handle, $headers);

        foreach ($satirlar as $satir) {
            fputcsv($handle, array_map(fn (string $header): mixed => $satir[$header] ?? '', $headers));
        }

        fclose($handle);
    }

    private function moduleName(string $uri): string
    {
        $parts = explode('/', trim($uri, '/'));

        return $parts[1] ?? 'admin';
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
