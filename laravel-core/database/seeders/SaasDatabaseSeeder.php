<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * SaaS çekirdek verisi: roller, modüller, yetkiler, matrisler, planlar.
 * Demo örnek veri {@see SaasDevSampleSeeder} içinde yalnızca local/testing ortamında çalışır.
 *
 * Çalıştırma (yalnızca bu şekilde):
 * - php artisan db:seed --class=SaasDatabaseSeeder
 */
class SaasDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SaasRolesSeeder::class,
            SaasModulesSeeder::class,
            SaasPermissionsSeeder::class,
            SaasRolePermissionMatrixSeeder::class,
            SaasPlansSeeder::class,
            SaasPlanModuleMatrixSeeder::class,
            MuhasebeOlcuBirimleriSeeder::class,
            SaasDevSampleSeeder::class,
        ]);
    }
}
