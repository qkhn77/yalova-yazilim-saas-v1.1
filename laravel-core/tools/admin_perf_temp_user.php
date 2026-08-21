<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$action = $argv[1] ?? 'ensure';
$email = 'perf-admin@local.test';
$username = 'perf_admin_measure';
$password = 'PerfAdmin123!';

if ($action === 'ensure') {
    $user = User::withoutGlobalScopes()
        ->withTrashed()
        ->where('email', $email)
        ->first();

    if (! $user) {
        $user = new User;
        $user->email = $email;
    }

    $user->name = 'Performance Admin';
    $user->ad_soyad = 'Performance Admin';
    $user->kullanici_adi = $username;
    $user->password = Hash::make($password);
    $user->super_admin_mi = true;

    if (method_exists($user, 'trashed') && $user->trashed()) {
        $user->restore();
    }

    $user->save();

    echo json_encode([
        'email' => $email,
        'username' => $username,
        'password' => $password,
        'user_id' => $user->getKey(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;

    return;
}

if ($action === 'delete') {
    $user = User::withoutGlobalScopes()
        ->withTrashed()
        ->where('email', $email)
        ->first();

    if ($user) {
        method_exists($user, 'forceDelete') ? $user->forceDelete() : $user->delete();
    }

    echo json_encode(['deleted' => (bool) $user], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;

    return;
}

fwrite(STDERR, "Unknown action: {$action}".PHP_EOL);
exit(1);
