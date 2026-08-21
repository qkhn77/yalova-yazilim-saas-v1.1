<?php

declare(strict_types=1);

use App\Models\Firma;
use App\Models\FirmaKullanici;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$action = $argv[1] ?? 'ensure';
$email = 'perf-tenant@local.test';
$username = 'perf_tenant_measure';
$password = 'PerfTenant123!';

if ($action === 'ensure') {
    $firma = Firma::query()
        ->where('durum', Firma::DURUM_AKTIF)
        ->orderBy('id')
        ->first();

    $rol = Rol::query()
        ->where('sistem_rolu_mu', true)
        ->whereIn('kod', ['firma_sahibi', 'firma_yoneticisi'])
        ->orderByRaw("FIELD(kod, 'firma_sahibi', 'firma_yoneticisi')")
        ->first();

    if (! $firma || ! $rol) {
        fwrite(STDERR, 'Aktif firma veya tenant rolü bulunamadı.'.PHP_EOL);
        exit(1);
    }

    $user = User::withoutGlobalScopes()
        ->withTrashed()
        ->where('email', $email)
        ->first();

    if (! $user) {
        $user = new User;
        $user->email = $email;
    }

    $user->name = 'Performance Tenant';
    $user->ad_soyad = 'Performance Tenant';
    $user->kullanici_adi = $username;
    $user->password = Hash::make($password);
    $user->super_admin_mi = false;
    if (Schema::hasColumn($user->getTable(), 'is_admin')) {
        $user->is_admin = false;
    }

    if (method_exists($user, 'trashed') && $user->trashed()) {
        $user->restore();
    }

    $user->save();

    $firmaKullanici = FirmaKullanici::withoutGlobalScopes()
        ->withTrashed()
        ->firstOrNew([
            'firma_id' => $firma->getKey(),
            'kullanici_id' => $user->getKey(),
        ]);

    $firmaKullanici->rol_id = $rol->getKey();
    $firmaKullanici->durum = 'aktif';
    $firmaKullanici->onay_durumu = 'aktif';
    $firmaKullanici->varsayilan_firma_mi = true;

    if (method_exists($firmaKullanici, 'trashed') && $firmaKullanici->trashed()) {
        $firmaKullanici->restore();
    }

    $firmaKullanici->save();

    echo json_encode([
        'email' => $email,
        'username' => $username,
        'password' => $password,
        'user_id' => $user->getKey(),
        'firma_id' => $firma->getKey(),
        'firma_kodu' => $firma->firma_kodu,
        'firma_kullanici_id' => $firmaKullanici->getKey(),
        'rol_id' => $rol->getKey(),
        'rol_kod' => $rol->kod,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;

    return;
}

if ($action === 'delete') {
    $user = User::withoutGlobalScopes()
        ->withTrashed()
        ->where('email', $email)
        ->first();

    if ($user) {
        FirmaKullanici::withoutGlobalScopes()
            ->withTrashed()
            ->where('kullanici_id', $user->getKey())
            ->forceDelete();

        method_exists($user, 'forceDelete') ? $user->forceDelete() : $user->delete();
    }

    echo json_encode(['deleted' => (bool) $user], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;

    return;
}

fwrite(STDERR, "Unknown action: {$action}".PHP_EOL);
exit(1);
