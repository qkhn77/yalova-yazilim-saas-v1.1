<?php
$base = dirname(__DIR__);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Post;
use Illuminate\Support\Facades\Storage;

$disk = Storage::disk('public');
$pool = collect($disk->files('posts'))
    ->filter(fn($p) => strtolower(pathinfo($p, PATHINFO_EXTENSION)) === 'jpg')
    ->values();

if ($pool->isEmpty()) {
    echo "No source images in posts/.\n";
    exit(1);
}

$maps = [];
foreach ([
    __DIR__.'/latest_user_posts.json',
    __DIR__.'/latest_user_posts_2.json',
    'C:/Users/RogStrix/Downloads/yalova_server_blog_ilk20_codex_ready.json',
    'C:/Users/RogStrix/Downloads/yalova_server_blog_ikinci20_codex_ready.json',
    'C:/Users/RogStrix/Downloads/yalova_server_blog_ucuncu20_codex_ready.json',
] as $jsonPath) {
    if (!is_file($jsonPath)) continue;
    $raw = file_get_contents($jsonPath);
    if ($raw === false) continue;
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    $data = json_decode($raw, true);
    if (!is_array($data)) continue;

    foreach ($data as $item) {
        if (!is_array($item)) continue;
        $slug = trim((string)($item['seo_url'] ?? ''));
        $file = trim((string)($item['gorsel_dosya_adi'] ?? ''));
        if ($slug === '' || $file === '') continue;
        if (!str_ends_with(strtolower($file), '.jpg')) $file .= '.jpg';
        $maps[$slug] = $file;
    }
}

$poolIdx = 0;
$created = 0;
$updated = 0;
$missingPosts = 0;

foreach ($maps as $slug => $file) {
    $post = Post::query()->where('slug', $slug)->first();
    if (!$post) {
        $missingPosts++;
        continue;
    }

    $target = 'posts/'.$file;
    if (!$disk->exists($target)) {
        $source = $pool[$poolIdx % $pool->count()];
        $poolIdx++;
        $bin = $disk->get($source);
        $disk->put($target, $bin);
        $created++;
        echo "CREATED {$target} <= {$source}\n";
    }

    $post->image = $file;
    $post->og_image = $file;
    $post->save();
    $updated++;
    echo "UPDATED {$slug} => {$file}\n";
}

echo "SUMMARY updated={$updated} created_files={$created} missing_posts={$missingPosts}\n";
