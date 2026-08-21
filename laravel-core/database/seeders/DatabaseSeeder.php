<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment(['local', 'testing'])) {
            $ekAlanlar = [];
            if (Schema::hasColumn('users', 'kullanici_adi')) {
                $ekAlanlar['kullanici_adi'] = 'test_user';
            }
            if (Schema::hasColumn('users', 'ad_soyad')) {
                $ekAlanlar['ad_soyad'] = 'Test User';
            }

            User::withoutGlobalScopes()->firstOrCreate(
                ['email' => 'test@example.com'],
                array_merge(
                    ['name' => 'Test User', 'password' => 'password'],
                    $ekAlanlar
                )
            );
        }

        $this->call([
            ServiceSeeder::class,
            ProjectSeeder::class,
            PageSeeder::class,
            MenuSeeder::class,
        ]);

        // SaaS çekirdek seed asla buradan çalıştırılmaz (üretim güvenliği).
        // php artisan db:seed --class=SaasDatabaseSeeder
    }
}
