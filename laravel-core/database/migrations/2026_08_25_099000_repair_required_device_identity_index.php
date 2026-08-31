<?php

declare(strict_types=1);

use Database\Migrations\Support\RequiredIndexRepairSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

require_once __DIR__.'/Support/RequiredIndexRepairSupport.php';

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            throw new RuntimeException('Required index repair requires an isolated MariaDB/MySQL database.');
        }

        $helper = new RequiredIndexRepairSupport(Schema::getConnection());
        $helper->ensureCanonicalIndex([
            'table' => 'teknik_servis_kayitli_cihazlar',
            'columns' => ['firma_id', 'cihaz_id', 'marka_id', 'model_no'],
            'unique' => false,
            'name' => 'ts_kayitli_cihazlar_kimlik_idx',
        ]);
    }

    public function down(): void
    {
        throw new RuntimeException('Required index rollback is intentionally unsupported; use verified restore-based recovery.');
    }
};
