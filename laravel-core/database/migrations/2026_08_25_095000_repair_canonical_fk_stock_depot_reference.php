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
            throw new RuntimeException('B06 requires an isolated MariaDB/MySQL database.');
        }

        $helper = new CanonicalForeignKeyRepairSupport(Schema::getConnection());

        foreach (self::manifest() as $definition) {
            $helper->ensureCanonicalForeignKey($definition);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('B06 corrective migration rollback is intentionally unsupported; use verified restore-based recovery.');
    }

    /** @return list<array<string, mixed>> */
    private static function manifest(): array
    {
        return [
            ['fk_id' => 'FK-038', 'child_table' => 'muhasebe_birimler', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-042', 'child_table' => 'muhasebe_logo_turleri', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-043', 'child_table' => 'muhasebe_malzeme_turleri', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-044', 'child_table' => 'muhasebe_markalar', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-045', 'child_table' => 'muhasebe_marka_ureticileri', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-046', 'child_table' => 'muhasebe_modeller', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-047', 'child_table' => 'muhasebe_modeller', 'child_columns' => ['marka_id'], 'parent_table' => 'muhasebe_markalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-051', 'child_table' => 'muhasebe_tasarimlar', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-052', 'child_table' => 'muhasebe_varyantlar', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-086', 'child_table' => 'stok_barkodlari', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'CASCADE', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-087', 'child_table' => 'stok_barkodlari', 'child_columns' => ['stok_id'], 'parent_table' => 'stok_kartlari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'CASCADE', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-088', 'child_table' => 'stok_hareketleri', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-089', 'child_table' => 'stok_hareketleri', 'child_columns' => ['iptal_edilen_hareket_id'], 'parent_table' => 'stok_hareketleri', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-090', 'child_table' => 'stok_hareketleri', 'child_columns' => ['stok_id'], 'parent_table' => 'stok_kartlari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-092', 'child_table' => 'stok_kartlari', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-093', 'child_table' => 'stok_kartlari', 'child_columns' => ['kategori_id'], 'parent_table' => 'stok_kategorileri', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-094', 'child_table' => 'stok_kartlari', 'child_columns' => ['logo_turu_id'], 'parent_table' => 'muhasebe_logo_turleri', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-095', 'child_table' => 'stok_kartlari', 'child_columns' => ['malzeme_turu_id'], 'parent_table' => 'muhasebe_malzeme_turleri', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-096', 'child_table' => 'stok_kartlari', 'child_columns' => ['marka_id'], 'parent_table' => 'muhasebe_markalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-097', 'child_table' => 'stok_kartlari', 'child_columns' => ['model_id'], 'parent_table' => 'muhasebe_modeller', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-098', 'child_table' => 'stok_kartlari', 'child_columns' => ['tasarim_id'], 'parent_table' => 'muhasebe_tasarimlar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-099', 'child_table' => 'stok_kartlari', 'child_columns' => ['tedarikci_id'], 'parent_table' => 'cariler', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-100', 'child_table' => 'stok_kartlari', 'child_columns' => ['varyant_id'], 'parent_table' => 'muhasebe_varyantlar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-101', 'child_table' => 'stok_kategorileri', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-102', 'child_table' => 'stok_kategorileri', 'child_columns' => ['parent_id'], 'parent_table' => 'stok_kategorileri', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
        ];
    }
};

