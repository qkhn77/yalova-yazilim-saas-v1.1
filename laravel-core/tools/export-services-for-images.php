<?php

declare(strict_types=1);

// UTF-8

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$items = App\Models\Service::query()
    ->with('category:id,slug')
    ->select(['id', 'title', 'slug', 'service_category_id', 'is_active'])
    ->orderBy('id')
    ->get()
    ->map(static function (App\Models\Service $service): array {
        return [
            'id' => $service->id,
            'title' => $service->title,
            'slug' => $service->slug,
            'category_slug' => $service->category?->slug,
            'is_active' => (bool) $service->is_active,
        ];
    })
    ->values()
    ->all();

echo json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
echo PHP_EOL;

