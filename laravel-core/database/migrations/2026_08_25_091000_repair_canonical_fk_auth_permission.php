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
            throw new RuntimeException('B02 requires an isolated MariaDB/MySQL database.');
        }

        $helper = new CanonicalForeignKeyRepairSupport(Schema::getConnection());

        foreach (self::manifest() as $definition) {
            $helper->ensureCanonicalForeignKey($definition);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('B02 corrective migration rollback is intentionally unsupported; use verified restore-based recovery.');
    }

    /** @return list<array<string, mixed>> */
    private static function manifest(): array
    {
        return [
            ['fk_id' => 'FK-021', 'child_table' => 'kullanici_yetkileri', 'child_columns' => ['kullanici_id'], 'parent_table' => 'users', 'parent_columns' => ['id'], 'canonical_on_delete' => 'CASCADE', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-022', 'child_table' => 'kullanici_yetkileri', 'child_columns' => ['yetki_id'], 'parent_table' => 'yetkiler', 'parent_columns' => ['id'], 'canonical_on_delete' => 'CASCADE', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-067', 'child_table' => 'role_user', 'child_columns' => ['role_id'], 'parent_table' => 'roles', 'parent_columns' => ['id'], 'canonical_on_delete' => 'CASCADE', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-068', 'child_table' => 'role_user', 'child_columns' => ['user_id'], 'parent_table' => 'users', 'parent_columns' => ['id'], 'canonical_on_delete' => 'CASCADE', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-069', 'child_table' => 'rol_yetkileri', 'child_columns' => ['rol_id'], 'parent_table' => 'roller', 'parent_columns' => ['id'], 'canonical_on_delete' => 'CASCADE', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-070', 'child_table' => 'rol_yetkileri', 'child_columns' => ['yetki_id'], 'parent_table' => 'yetkiler', 'parent_columns' => ['id'], 'canonical_on_delete' => 'CASCADE', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-072', 'child_table' => 'sepet_kalemleri', 'child_columns' => ['sepet_id'], 'parent_table' => 'sepetler', 'parent_columns' => ['id'], 'canonical_on_delete' => 'CASCADE', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-091', 'child_table' => 'stok_karti_gorselleri', 'child_columns' => ['stok_karti_id'], 'parent_table' => 'stok_kartlari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'CASCADE', 'canonical_on_update' => 'RESTRICT'],
        ];
    }
};

