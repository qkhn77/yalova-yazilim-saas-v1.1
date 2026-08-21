<?php
$base = dirname(__DIR__);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$slugs = ['yalova-laptop-tamir-hizmeti','yalova-it-destek-secimi','yalova-server-log-analizi-nasil-yapilir'];
$posts = App\Models\Post::whereIn('slug',$slugs)->get(['slug','image']);
foreach($posts as $p){ echo $p->slug.'|'.$p->image."\n"; }
