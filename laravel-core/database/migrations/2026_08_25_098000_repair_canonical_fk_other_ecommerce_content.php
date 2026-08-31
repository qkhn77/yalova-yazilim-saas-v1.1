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
            throw new RuntimeException('B09 requires an isolated MariaDB/MySQL database.');
        }

        $helper = new CanonicalForeignKeyRepairSupport(Schema::getConnection());

        foreach (self::manifest() as $definition) {
            $helper->ensureCanonicalForeignKey($definition);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('B09 corrective migration rollback is intentionally unsupported; use verified restore-based recovery.');
    }

    /** @return list<array<string, mixed>> */
    private static function manifest(): array
    {
        return [
            ['fk_id' => 'FK-057', 'child_table' => 'posts', 'child_columns' => ['post_category_id'], 'parent_table' => 'post_categories', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-064', 'child_table' => 'products', 'child_columns' => ['category_id'], 'parent_table' => 'product_categories', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-065', 'child_table' => 'product_categories', 'child_columns' => ['parent_id'], 'parent_table' => 'product_categories', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-066', 'child_table' => 'projects', 'child_columns' => ['project_category_id'], 'parent_table' => 'project_categories', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-071', 'child_table' => 'sepetler', 'child_columns' => ['kullanici_id'], 'parent_table' => 'users', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-073', 'child_table' => 'sepet_kalemleri', 'child_columns' => ['stok_karti_id'], 'parent_table' => 'stok_kartlari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-074', 'child_table' => 'services', 'child_columns' => ['service_category_id'], 'parent_table' => 'service_categories', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-085', 'child_table' => 'sistem_olaylari', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
        ];
    }
};

