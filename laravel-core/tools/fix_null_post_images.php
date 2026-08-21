<?php
$base = dirname(__DIR__);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Post;
use Illuminate\Support\Facades\Storage;

$disk = Storage::disk('public');
$files = collect($disk->files('posts'))
    ->filter(fn ($p) => strtolower(pathinfo($p, PATHINFO_EXTENSION)) === 'jpg')
    ->map(fn ($p) => basename($p))
    ->values();

if ($files->isEmpty()) {
    echo "No images found under posts/.\n";
    exit(0);
}

// JSON'lardan slug=>gorsel_dosya_adi eşleşmeleri
$slugToImage = [];
foreach ([
    __DIR__.'/latest_user_posts.json',
    __DIR__.'/latest_user_posts_2.json',
    'C:/Users/RogStrix/Downloads/yalova_server_blog_ikinci20_codex_ready.json',
] as $jsonPath) {
    if (!is_file($jsonPath)) {
        continue;
    }
    $raw = file_get_contents($jsonPath);
    if ($raw === false) {
        continue;
    }
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        continue;
    }
    foreach ($data as $item) {
        if (!is_array($item)) {
            continue;
        }
        $slug = trim((string)($item['seo_url'] ?? ''));
        $img = trim((string)($item['gorsel_dosya_adi'] ?? ''));
        if ($slug !== '' && $img !== '') {
            $slugToImage[$slug] = $img;
        }
    }
}

$posts = Post::query()
    ->whereNull('image')
    ->whereHas('category', fn($q) => $q->where('slug', 'sunucu'))
    ->orderBy('id')
    ->get();

$used = Post::query()->whereNotNull('image')->pluck('image')->filter()->values()->all();
$usedSet = array_fill_keys($used, true);

$pool = $files->values()->all();
$poolIndex = 0;

$updated = 0;

function firstExisting(array $candidates, array $poolSet): ?string {
    foreach ($candidates as $c) {
        if ($c !== '' && isset($poolSet[$c])) {
            return $c;
        }
    }
    return null;
}

$poolSet = array_fill_keys($pool, true);

foreach ($posts as $post) {
    $slug = (string)$post->slug;

    $candidates = [];

    if (isset($slugToImage[$slug])) {
        $candidates[] = $slugToImage[$slug];
    }

    $candidates[] = $slug.'.jpg';
    $candidates[] = str_replace('yalovada-', 'yalova-da-', $slug).'.jpg';
    $candidates[] = str_replace('yalovada-', 'yalova-da-', str_replace('-', '-', $slug)).'.jpg';

    $chosen = firstExisting($candidates, $poolSet);

    // Eğer doğrudan uygun yoksa, havuzdan kullanılmamış ilk görseli ata
    if ($chosen === null) {
        while ($poolIndex < count($pool) && isset($usedSet[$pool[$poolIndex]])) {
            $poolIndex++;
        }
        if ($poolIndex >= count($pool)) {
            $poolIndex = 0;
        }
        $chosen = $pool[$poolIndex] ?? null;
        $poolIndex++;
    }

    if ($chosen === null) {
        continue;
    }

    // mümkünse aynı görsel tekrarını azalt
    if (isset($usedSet[$chosen])) {
        $alt = null;
        for ($i = 0; $i < count($pool); $i++) {
            $candidate = $pool[($poolIndex + $i) % count($pool)];
            if (!isset($usedSet[$candidate])) {
                $alt = $candidate;
                break;
            }
        }
        if ($alt !== null) {
            $chosen = $alt;
        }
    }

    $post->image = $chosen;
    if (empty($post->og_image)) {
        $post->og_image = $chosen;
    }
    $post->save();

    $usedSet[$chosen] = true;
    $updated++;

    echo "UPDATED {$post->id} {$slug} => {$chosen}\n";
}

echo "TOTAL_UPDATED={$updated}\n";
