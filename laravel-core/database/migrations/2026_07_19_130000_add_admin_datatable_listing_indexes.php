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
        'cari_hareketleri' => [
            'columns' => ['firma_id', 'durum', 'islem_tarihi', 'id'],
            'name' => 'cari_hrk_firma_durum_islem_id_idx',
        ],
        'finans_hareketleri' => [
            'columns' => ['firma_id', 'durum', 'tarih', 'id'],
            'name' => 'finans_hrk_firma_durum_tarih_id_idx',
        ],
        'banka_hareketleri' => [
            'columns' => ['firma_id', 'banka_hesap_id', 'durum'],
            'name' => 'banka_hrk_firma_hesap_durum_idx',
        ],
        'kasa_hareketleri' => [
            'columns' => ['firma_id', 'kasa_hesap_id', 'durum'],
            'name' => 'kasa_hrk_firma_hesap_durum_idx',
        ],
        'pos_hareketleri' => [
            'columns' => ['firma_id', 'pos_hesap_id', 'durum'],
            'name' => 'pos_hrk_firma_hesap_durum_idx',
        ],
        'muhasebe_barkodlu_satislar' => [
            'columns' => ['firma_id', 'durum', 'satis_tarihi', 'id'],
            'name' => 'muh_barkod_satis_firma_durum_tarih_id_idx',
        ],
        'muhasebe_alacak_plan_taksitleri' => [
            'columns' => ['firma_id', 'durum', 'vade_tarihi', 'id'],
            'name' => 'muh_alacak_taksit_firma_durum_vade_id_idx',
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
