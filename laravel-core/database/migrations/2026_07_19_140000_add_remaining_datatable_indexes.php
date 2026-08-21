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
        'ecommerce_mesaj_konulari' => [
            'columns' => ['firma_id', 'konu_tipi', 'updated_at', 'id'],
            'name' => 'ecom_mesaj_konu_firma_tip_guncel_idx',
        ],
        'muhasebe_barkodlu_satis_iadeler' => [
            'columns' => ['firma_id', 'iade_tarihi', 'id'],
            'name' => 'muh_barkod_iade_firma_tarih_id_idx',
        ],
        'nette_fatura_gonderimleri' => [
            'columns' => ['firma_id', 'id'],
            'name' => 'nette_gonderim_firma_id_idx',
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
