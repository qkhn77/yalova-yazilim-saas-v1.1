<?php

$base = dirname(__DIR__);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Post;
use App\Models\PostCategory;
use Illuminate\Support\Str;

if ($argc < 2) {
    fwrite(STDERR, "Usage: php tools/import_posts_from_json.php <json_path>\n");
    exit(1);
}

$jsonPath = $argv[1];
if (!is_file($jsonPath)) {
    fwrite(STDERR, "JSON file not found: {$jsonPath}\n");
    exit(1);
}

$raw = file_get_contents($jsonPath);
if ($raw === false) {
    fwrite(STDERR, "Cannot read JSON file: {$jsonPath}\n");
    exit(1);
}

$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
$data = json_decode($raw, true);
if (!is_array($data)) {
    fwrite(STDERR, "Invalid JSON format.\n");
    exit(1);
}

$category = PostCategory::query()->updateOrCreate(
    ['slug' => 'sunucu'],
    [
        'name' => 'Sunucu',
        'meta_title' => 'Yalova Sunucu Blog İçerikleri',
        'description' => 'Yalova odaklı sunucu kurulumu, bakım, arıza ve performans içerikleri.',
        'meta_description' => 'Yalova’da sunucu kurulumu, bakım, arıza ve veri güvenliği konularında profesyonel blog içerikleri.',
        'meta_keywords' => 'yalova sunucu, server kurulumu, server bakım, server arıza',
        'sort_order' => 10,
        'is_active' => true,
    ]
);

$created = 0;
$updated = 0;
$processed = 0;

foreach ($data as $i => $item) {
    if (!is_array($item)) {
        continue;
    }

    $title = trim((string) ($item['baslik'] ?? ''));
    if ($title === '') {
        continue;
    }

    $slug = trim((string) ($item['seo_url'] ?? ''));
    if ($slug === '') {
        $slug = Str::slug($title);
    }

    $keywordsRaw = $item['anahtar_kelimeler'] ?? [];
    if (is_array($keywordsRaw)) {
        $keywords = implode(', ', array_map(static fn ($k) => trim((string) $k), $keywordsRaw));
    } else {
        $keywords = trim((string) $keywordsRaw);
    }

    $payload = [
        'post_category_id' => $category->id,
        'title' => $title,
        'excerpt' => mb_substr(trim((string) ($item['meta_aciklama'] ?? '')), 0, 255),
        'meta_keywords' => mb_substr($keywords, 0, 500),
        'content' => (string) ($item['icerik_html'] ?? ''),
        'image' => null,
        'og_title' => mb_substr(trim((string) ($item['og_title'] ?? $title)), 0, 100),
        'og_description' => mb_substr(trim((string) ($item['og_description'] ?? '')), 0, 500),
        'og_image' => null,
        'meta_robots' => 'index,follow',
        'is_published' => true,
        'published_at' => now()->subMinutes(max(0, count($data) - $i)),
        'sort_order' => ($i + 1) * 10,
    ];

    $existing = Post::query()->where('slug', $slug)->first();
    if ($existing) {
        $existing->fill($payload);
        $existing->slug = $slug;
        $existing->save();
        $updated++;
    } else {
        Post::query()->create(array_merge($payload, ['slug' => $slug]));
        $created++;
    }

    $processed++;
}

echo 'category_id='.$category->id.PHP_EOL;
echo 'processed='.$processed.PHP_EOL;
echo 'created='.$created.PHP_EOL;
echo 'updated='.$updated.PHP_EOL;

