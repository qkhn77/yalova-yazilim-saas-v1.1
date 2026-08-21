<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function login_performance_admin(): void
{
    $userClass = \App\Models\User::class;

    if (! class_exists($userClass)) {
        return;
    }

    $user = $userClass::withoutGlobalScopes()
        ->where('email', 'perf-admin@local.test')
        ->first();

    if ($user instanceof \Illuminate\Contracts\Auth\Authenticatable) {
        Auth::login($user);
    }
}

function invoke_static_method(string $class, string $method): mixed
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

    return $reflection->invoke(null);
}

function authorization_method_for_route(string $uri, ?string $name): ?string
{
    if (str_ends_with($uri, '/edit') || (is_string($name) && str_ends_with($name, '.edit'))) {
        return 'canEdit';
    }

    if (is_string($name) && str_ends_with($name, '.view')) {
        return 'canView';
    }

    return null;
}

function record_is_authorized_for_route(?string $resourceClass, Model $record, ?string $method): bool
{
    if (! is_string($resourceClass) || ! is_string($method) || ! method_exists($resourceClass, $method)) {
        return true;
    }

    try {
        return (bool) invoke_static_method_with_args($resourceClass, $method, [$record]);
    } catch (Throwable) {
        return false;
    }
}

function invoke_static_method_with_args(string $class, string $method, array $args): mixed
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

function first_authorized_record_from_query(Builder $query, ?string $resourceClass, ?string $authorizationMethod): ?Model
{
    $queryModel = $query->getModel();
    $records = (clone $query)
        ->orderBy($queryModel->qualifyColumn($queryModel->getKeyName()))
        ->limit(50)
        ->get();

    foreach ($records as $record) {
        if ($record instanceof Model && record_is_authorized_for_route($resourceClass, $record, $authorizationMethod)) {
            return $record;
        }
    }

    return null;
}

/**
 * @return array{record: Model|null, status: string}
 */
function first_record_for_route(?string $resourceClass, string $modelClass, string $uri, ?string $name): array
{
    if (! is_subclass_of($modelClass, Model::class)) {
        return ['record' => null, 'status' => 'no_record'];
    }

    $model = new $modelClass;
    $keyName = $model->getKeyName();
    $authorizationMethod = authorization_method_for_route($uri, $name);
    $hadUnauthorizedRecords = false;

    if (is_string($resourceClass) && class_exists($resourceClass)) {
        try {
            $query = invoke_static_method($resourceClass, 'getEloquentQuery');

            if ($query instanceof Builder) {
                $record = first_authorized_record_from_query($query, $resourceClass, $authorizationMethod);

                if ($record instanceof Model) {
                    return ['record' => $record, 'status' => 'ok'];
                }

                $hadUnauthorizedRecords = $authorizationMethod !== null && (clone $query)->limit(1)->exists();
            }
        } catch (Throwable) {
            // Some resources need request-only state. Fall back to the model query below.
        }
    }

    try {
        $query = $modelClass::query()->orderBy($keyName)->limit(50);

        foreach ($query->get() as $record) {
            if ($record instanceof Model && record_is_authorized_for_route($resourceClass, $record, $authorizationMethod)) {
                return ['record' => $record, 'status' => 'ok'];
            }

            if ($record instanceof Model) {
                $hadUnauthorizedRecords = $authorizationMethod !== null;
            }
        }
    } catch (Throwable) {
        // Some models have scopes that need application state. Fall back below.
    }

    try {
        $records = $modelClass::withoutGlobalScopes()
            ->orderBy($keyName)
            ->limit(50)
            ->get();

        foreach ($records as $record) {
            if ($record instanceof Model && record_is_authorized_for_route($resourceClass, $record, $authorizationMethod)) {
                return ['record' => $record, 'status' => 'ok'];
            }

            if ($record instanceof Model) {
                $hadUnauthorizedRecords = $authorizationMethod !== null;
            }
        }
    } catch (Throwable) {
        // Ignore and report the best status below.
    }

    return ['record' => null, 'status' => $hadUnauthorizedRecords ? 'no_authorization' : 'no_record'];
}

login_performance_admin();

$samples = [];

foreach (Route::getRoutes() as $route) {
    $uri = $route->uri();
    $name = $route->getName();
    $methods = $route->methods();

    if (
        ! in_array('GET', $methods, true) ||
        ! str_starts_with($uri, 'admin') ||
        ! str_contains($uri, '{') ||
        ! is_string($name) ||
        ! str_starts_with($name, 'filament.admin.')
    ) {
        continue;
    }

    $action = $route->getActionName();
    $actionClass = str_contains($action, '@') ? strstr($action, '@', true) : $action;
    $resourceClass = is_string($actionClass) ? invoke_static_method($actionClass, 'getResource') : null;
    $modelClass = is_string($resourceClass) ? invoke_static_method($resourceClass, 'getModel') : null;
    $sample = is_string($modelClass)
        ? first_record_for_route($resourceClass, $modelClass, $uri, $name)
        : ['record' => null, 'status' => 'no_model'];
    $record = $sample['record'];
    $resolvedUri = null;
    $recordKey = null;
    $status = $sample['status'];

    if ($record) {
        $recordKey = (string) $record->getRouteKey();
        $resolvedUri = preg_replace('/\{record[^}]*\}/', rawurlencode($recordKey), $uri);
        $status = 'ok';
    } elseif (! is_string($modelClass)) {
        $status = 'no_model';
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

usort($samples, fn (array $a, array $b): int => strcmp($a['uri_template'], $b['uri_template']));

echo json_encode($samples, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).PHP_EOL;
