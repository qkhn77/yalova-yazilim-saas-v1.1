<?php

declare(strict_types=1);

use Database\Migrations\Support\CanonicalForeignKeyRepairSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

require_once __DIR__.'/Support/CanonicalForeignKeyRepairSupport.php';

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            throw new RuntimeException('B03 requires an isolated MariaDB/MySQL database.');
        }

        $helper = new CanonicalForeignKeyRepairSupport(Schema::getConnection());

        foreach (self::manifest() as $definition) {
            $helper->ensureCanonicalForeignKey($definition);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('B03 corrective migration rollback is intentionally unsupported; use verified restore-based recovery.');
    }

    /** @return list<array<string, mixed>> */
    private static function manifest(): array
    {
        return [
            ['fk_id' => 'FK-048', 'child_table' => 'muhasebe_odeme_yontemleri', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'CASCADE', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-049', 'child_table' => 'muhasebe_para_birimleri', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'CASCADE', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-055', 'child_table' => 'plan_modulleri', 'child_columns' => ['modul_id'], 'parent_table' => 'moduller', 'parent_columns' => ['id'], 'canonical_on_delete' => 'CASCADE', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-056', 'child_table' => 'plan_modulleri', 'child_columns' => ['plan_id'], 'parent_table' => 'planlar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'CASCADE', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-109', 'child_table' => 'teknik_servis_baski_sablonlari', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'CASCADE', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-149', 'child_table' => 'teknik_servis_mesaj_sablonlari', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'CASCADE', 'canonical_on_update' => 'RESTRICT'],
        ];
    }
};

