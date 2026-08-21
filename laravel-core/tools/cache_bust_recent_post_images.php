<?php
$base = dirname(__DIR__);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Post;
use Illuminate\Support\Facades\Storage;

$maps = [];
foreach ([
    __DIR__.'/latest_user_posts.json',
    __DIR__.'/latest_user_posts_2.json',
    'C:/Users/RogStrix/Downloads/yalova_server_blog_ikinci20_codex_ready.json',
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
        if ($slug !== '') $maps[] = $slug;
    }
}
$maps = array_values(array_unique($maps));

$disk = Storage::disk('public');
$updated = 0;

foreach ($maps as $slug) {
    $post = Post::where('slug', $slug)->first();
    if (!$post || !$post->image) continue;

    $current = 'posts/'.ltrim((string)$post->image, '/');
    if (!$disk->exists($current)) continue;

    $name = pathinfo((string)$post->image, PATHINFO_FILENAME);
    $ext = pathinfo((string)$post->image, PATHINFO_EXTENSION) ?: 'jpg';
    $newFile = $name.'-cb'.date('YmdHis').'-'.$post->id.'.'.$ext;
    $newPath = 'posts/'.$newFile;

    $bin = $disk->get($current);
    $disk->put($newPath, $bin);

    $post->image = $newFile;
    $post->og_image = $newFile;
    $post->save();

    $updated++;
    echo "UPDATED {$post->id} {$slug} => {$newFile}\n";
}

echo "TOTAL_UPDATED={$updated}\n";
