<?php
$base = dirname(__DIR__);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Post;
use Illuminate\Support\Facades\Storage;

$pool = collect(Storage::disk('public')->files('posts'))
    ->filter(fn($p) => strtolower(pathinfo($p, PATHINFO_EXTENSION)) === 'jpg')
    ->map(fn($p) => basename($p))
    ->unique()
    ->values();

$targets = Post::query()
    ->whereBetween('id', [87, 122])
    ->whereHas('category', fn($q) => $q->where('slug', 'sunucu'))
    ->orderBy('id')
    ->get();

if ($targets->isEmpty() || $pool->isEmpty()) {
    echo "No targets or no image pool.\n";
    exit(0);
}

$poolCount = $pool->count();
$updated = 0;

foreach ($targets as $idx => $post) {
    $img = $pool[$idx % $poolCount];
    $post->image = $img;
    $post->og_image = $img;
    $post->save();
    $updated++;
    echo "UPDATED {$post->id} {$post->slug} => {$img}\n";
}

echo "TOTAL_UPDATED={$updated}\n";
