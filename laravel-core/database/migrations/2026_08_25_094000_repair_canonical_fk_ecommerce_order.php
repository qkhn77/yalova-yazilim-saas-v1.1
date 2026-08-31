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
            throw new RuntimeException('B05 requires an isolated MariaDB/MySQL database.');
        }

        $helper = new CanonicalForeignKeyRepairSupport(Schema::getConnection());

        foreach (self::manifest() as $definition) {
            $helper->ensureCanonicalForeignKey($definition);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('B05 corrective migration rollback is intentionally unsupported; use verified restore-based recovery.');
    }

    /** @return list<array<string, mixed>> */
    private static function manifest(): array
    {
        return [
            ['fk_id' => 'FK-075', 'child_table' => 'siparisler', 'child_columns' => ['ecommerce_odeme_yontemi_id'], 'parent_table' => 'ecommerce_odeme_yontemleri', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-076', 'child_table' => 'siparisler', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-077', 'child_table' => 'siparisler', 'child_columns' => ['kullanici_id'], 'parent_table' => 'users', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-078', 'child_table' => 'siparisler', 'child_columns' => ['muhasebe_cari_id'], 'parent_table' => 'cariler', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-079', 'child_table' => 'siparisler', 'child_columns' => ['proforma_fatura_id'], 'parent_table' => 'faturalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-080', 'child_table' => 'siparisler', 'child_columns' => ['tahsilat_finans_hareketi_id'], 'parent_table' => 'finans_hareketleri', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-081', 'child_table' => 'siparis_gecmisleri', 'child_columns' => ['kullanici_id'], 'parent_table' => 'users', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-084', 'child_table' => 'siparis_kalemleri', 'child_columns' => ['stok_karti_id'], 'parent_table' => 'stok_kartlari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
        ];
    }
};

