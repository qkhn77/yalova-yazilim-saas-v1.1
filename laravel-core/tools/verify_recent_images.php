<?php
$base = dirname(__DIR__);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rows = App\Models\Post::query()
    ->whereBetween('id',[87,122])
    ->whereHas('category', fn($q)=>$q->where('slug','sunucu'))
    ->get(['id','slug','image']);

$total = $rows->count();
$distinct = $rows->pluck('image')->filter()->unique()->count();
$nulls = $rows->whereNull('image')->count();

echo "total={$total}\n";
echo "distinct_images={$distinct}\n";
echo "null_images={$nulls}\n";
