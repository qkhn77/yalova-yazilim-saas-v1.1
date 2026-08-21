<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEXES = [
        'siparisler' => [
            'columns' => ['firma_id', 'durum'],
            'name' => 'siparisler_firma_durum_idx',
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
