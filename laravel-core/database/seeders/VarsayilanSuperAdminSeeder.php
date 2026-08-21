<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Tek seferlik / yerel ortam: süper yönetici oluşturur veya günceller.
 * Çalıştırma: php artisan db:seed --class=VarsayilanSuperAdminSeeder
 */
class VarsayilanSuperAdminSeeder extends Seeder
{
    public const EMAIL = 'admin@yalovakamera.local';

    public function run(): void
    {
        $user = User::withoutGlobalScopes()
            ->withTrashed()
            ->where('email', self::EMAIL)
            ->first();

        $alanlar = [
            'name' => 'admin',
            'kullanici_adi' => 'admin',
            'ad_soyad' => 'Admin',
            'password' => '123456',
            'super_admin_mi' => true,
        ];

        if ($user) {
            $user->fill($alanlar);
            if ($user->trashed()) {
                $user->restore();
            }
            $user->save();
        } else {
            User::withoutGlobalScopes()->create(array_merge(
                ['email' => self::EMAIL],
                $alanlar
            ));
        }

        $this->command?->info('Süper admin hazır: '.self::EMAIL.' (şifre: 123456) — Üretimde şifreyi değiştirin.');
    }
}
