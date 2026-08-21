<?php
$base = dirname(__DIR__);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$recent = App\Models\Post::query()->orderByDesc('id')->limit(40)->get(['id','slug','title','image','published_at']);
foreach($recent as $p){
    echo $p->id.'|'.$p->slug.'|'.($p->image ?? 'NULL')."\n";
}
