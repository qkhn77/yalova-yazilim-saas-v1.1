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
            throw new RuntimeException('B01 requires an isolated MariaDB/MySQL database.');
        }

        $helper = new CanonicalForeignKeyRepairSupport(Schema::getConnection());

        foreach (self::manifest() as $definition) {
            $helper->ensureCanonicalForeignKey($definition);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('B01 corrective migration rollback is intentionally unsupported; use verified restore-based recovery.');
    }

    /** @return list<array<string, mixed>> */
    private static function manifest(): array
    {
        return [
            ['fk_id' => 'FK-006', 'child_table' => 'firmalar', 'child_columns' => ['onaylayan_kullanici_id'], 'parent_table' => 'users', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-007', 'child_table' => 'firma_abonelikleri', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'CASCADE', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-008', 'child_table' => 'firma_abonelikleri', 'child_columns' => ['plan_id'], 'parent_table' => 'planlar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-009', 'child_table' => 'firma_ayarlari', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'CASCADE', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-010', 'child_table' => 'firma_kullanicilari', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'CASCADE', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-011', 'child_table' => 'firma_kullanicilari', 'child_columns' => ['kullanici_id'], 'parent_table' => 'users', 'parent_columns' => ['id'], 'canonical_on_delete' => 'CASCADE', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-012', 'child_table' => 'firma_kullanicilari', 'child_columns' => ['rol_id'], 'parent_table' => 'roller', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-013', 'child_table' => 'firma_modulleri', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'CASCADE', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-014', 'child_table' => 'firma_modulleri', 'child_columns' => ['modul_id'], 'parent_table' => 'moduller', 'parent_columns' => ['id'], 'canonical_on_delete' => 'CASCADE', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-020', 'child_table' => 'kullanici_yetkileri', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'CASCADE', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-023', 'child_table' => 'menu_items', 'child_columns' => ['parent_id'], 'parent_table' => 'menu_items', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-041', 'child_table' => 'muhasebe_etiket_sablonlari', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'CASCADE', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-050', 'child_table' => 'muhasebe_satis_fis_sablonlari', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'CASCADE', 'canonical_on_update' => 'RESTRICT'],
        ];
    }
};

