<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, array{columns: array<int, string>, name: string}>
     */
    private const INDEXES = [
        'denetim_kayitlari' => [
            'columns' => ['firma_id', 'created_at', 'id'],
            'name' => 'denetim_firma_tarih_id_idx',
        ],
        'ecommerce_bildirim_loglari' => [
            'columns' => ['firma_id', 'id'],
            'name' => 'ecom_bildirim_firma_id_idx',
        ],
        'siparisler' => [
            'columns' => ['firma_id', 'created_at', 'id'],
            'name' => 'siparis_firma_tarih_id_idx',
        ],
        'stok_kartlari' => [
            'columns' => ['firma_id', 'created_at', 'id'],
            'name' => 'stok_karti_firma_tarih_id_idx',
        ],
        'stok_kategorileri' => [
            'columns' => ['firma_id', 'parent_id', 'ad'],
            'name' => 'stok_kategori_firma_parent_ad_idx',
        ],
        'stok_barkodlari' => [
            'columns' => ['firma_id', 'updated_at', 'id'],
            'name' => 'stok_barkod_firma_guncel_id_idx',
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $tableName => $index) {
            if (! Schema::hasTable($tableName) || $this->indexVarMi($tableName, $index['name'])) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($index): void {
                $table->index($index['columns'], $index['name']);
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $tableName => $index) {
            if (! Schema::hasTable($tableName) || ! $this->indexVarMi($tableName, $index['name'])) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($index): void {
                $table->dropIndex($index['name']);
            });
        }
    }

    private function indexVarMi(string $tableName, string $indexName): bool
    {
        return collect(Schema::getIndexes($tableName))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $indexName);
    }
};
