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
    'C:/Users/RogStrix/Downloads/yalova_server_blog_ilk20_codex_ready.json',
    'C:/Users/RogStrix/Downloads/yalova_server_blog_ikinci20_codex_ready.json',
    'C:/Users/RogStrix/Downloads/yalova_server_blog_ucuncu20_codex_ready.json',
] as $jsonPath) {
    if (!is_file($jsonPath)) continue;
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($jsonPath));
    $data = json_decode($raw, true);
    if (!is_array($data)) continue;
    foreach ($data as $item) {
        $slug = trim((string)($item['seo_url'] ?? ''));
        $file = trim((string)($item['gorsel_dosya_adi'] ?? ''));
        if ($slug === '' || $file === '') continue;
        if (!str_ends_with(strtolower($file), '.jpg')) $file .= '.jpg';
        $maps[$slug] = $file;
    }
}

$ok = 0; $mismatch = 0; $missingFile = 0;
$disk = Storage::disk('public');

foreach ($maps as $slug => $expected) {
    $post = Post::where('slug',$slug)->first();
    if (!$post) continue;
    $isMatch = ((string)$post->image === $expected);
    $hasFile = $disk->exists('posts/'.$expected);
    if ($isMatch && $hasFile) {
        $ok++;
    } else {
        if (!$isMatch) $mismatch++;
        if (!$hasFile) $missingFile++;
        echo "ISSUE {$slug} image=".($post->image ?? 'NULL')." expected={$expected} exists=".($hasFile?'1':'0')."\n";
    }
}

echo "OK={$ok} MISMATCH={$mismatch} MISSING_FILE={$missingFile}\n";
