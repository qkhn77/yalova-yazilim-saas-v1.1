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
        'stok_kartlari' => [
            'columns' => ['firma_id', 'ad', 'deleted_at'],
            'name' => 'stok_karti_firma_ad_deleted_idx',
        ],
        'muhasebe_birimler' => [
            'columns' => ['firma_id', 'aktif_mi', 'ad', 'deleted_at'],
            'name' => 'muh_birim_firma_aktif_ad_deleted_idx',
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
