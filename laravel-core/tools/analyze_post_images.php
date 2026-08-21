<?php

$base = dirname(__DIR__);
$jsonFiles = [
    $base.'/tools/latest_user_posts.json',
    $base.'/tools/latest_user_posts_2.json',
    'C:/Users/RogStrix/Downloads/yalova_server_blog_ikinci20_codex_ready.json',
];

$maps = [];
foreach ($jsonFiles as $jsonPath) {
    if (! is_file($jsonPath)) {
        continue;
    }

    $raw = (string) file_get_contents($jsonPath);
    $raw = (string) preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    $data = json_decode($raw, true);
    if (! is_array($data)) {
        continue;
    }

    foreach ($data as $item) {
        if (! is_array($item)) {
            continue;
        }
        $slug = trim((string) ($item['seo_url'] ?? ''));
        $file = trim((string) ($item['gorsel_dosya_adi'] ?? ''));
        if ($slug === '' || $file === '') {
            continue;
        }
        if (! str_ends_with(strtolower($file), '.jpg')) {
            $file .= '.jpg';
        }
        $maps[$slug] = $file;
    }
}

$dir = $base.'/storage/app/public/posts/';
$hashCounts = [];

foreach ($maps as $slug => $file) {
    $path = $dir.$file;
    if (! is_file($path)) {
        echo "MISS|{$slug}|{$file}\n";
        continue;
    }

    $hash = md5_file($path) ?: 'NOHASH';
    $size = filesize($path) ?: 0;
    echo "OK|{$slug}|{$file}|{$size}|{$hash}\n";
    $hashCounts[$hash] = ($hashCounts[$hash] ?? 0) + 1;
}

echo "---DUP-HASH---\n";
foreach ($hashCounts as $hash => $count) {
    if ($count > 1) {
        echo "{$hash}|{$count}\n";
    }
}

