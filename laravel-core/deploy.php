<?php

declare(strict_types=1);

const DEPLOY_KEY = 'aec875aeb2b1617898133c21f55a2c2edd4cfd2a0a22c3b0a8e9d7a861e1595d';

header('Content-Type: text/plain; charset=utf-8');

if (! hash_equals(DEPLOY_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(404);
    echo "Bulunamadi.\n";
    exit;
}

set_time_limit(300);
ini_set('display_errors', '0');

$basePath = __DIR__;
if (! is_file($basePath.'/artisan') && is_file(dirname(__DIR__).'/artisan')) {
    $basePath = dirname(__DIR__);
}

if (! is_file($basePath.'/artisan') || ! is_file($basePath.'/bootstrap/app.php')) {
    http_response_code(500);
    echo "Laravel proje kok dizini bulunamadi.\n";
    exit;
}

chdir($basePath);

require $basePath.'/vendor/autoload.php';

$app = require $basePath.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$commands = [
    ['optimize:clear', []],
    ['migrate', ['--force' => true]],
    ['storage:link', []],
    // Temizlenen önbellekleri deploy sonunda yeniden oluştur; ilk admin isteği
    // derleme maliyetini kullanıcıya yansıtmasın.
    ['optimize', []],
];

echo "Yalova Kamera deploy basladi: ".date('Y-m-d H:i:s')."\n";
echo "Proje: {$basePath}\n\n";

foreach ($commands as [$command, $arguments]) {
    echo ">>> php artisan {$command}\n";

    try {
        $status = $kernel->call($command, $arguments);
        $output = trim($kernel->output());

        if ($output !== '') {
            echo $output."\n";
        }

        echo $status === 0 ? "OK\n\n" : "HATA KODU: {$status}\n\n";

        if ($status !== 0 && $command !== 'storage:link') {
            http_response_code(500);
            echo "Islem durduruldu. Hatayi kontrol edin.\n";
            exit;
        }
    } catch (Throwable $e) {
        if ($command === 'storage:link' && str_contains($e->getMessage(), 'already exists')) {
            echo "Storage link zaten var, devam ediliyor.\n\n";
            continue;
        }

        http_response_code(500);
        echo "HATA: ".$e->getMessage()."\n";
        echo "Dosya: ".$e->getFile().':'.$e->getLine()."\n";
        exit;
    }
}

echo "Deploy tamamlandi: ".date('Y-m-d H:i:s')."\n";
echo "Guvenlik icin deploy.php dosyasini FTP'den hemen silin.\n";
