<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * SaaS çekirdek verisi: roller, modüller, yetkiler, matrisler, planlar.
 * Demo örnek veri {@see SaasDevSampleSeeder} ile ayrıca ve yalnızca local/testing
 * ortamında çalıştırılabilir; çekirdek zincirin parçası değildir.
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
        ]);
    }
}
