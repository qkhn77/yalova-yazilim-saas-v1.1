<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('faturalar')) {
            return;
        }

        Schema::table('faturalar', function (Blueprint $table): void {
            $this->indexEkle($table, 'faturalar_firma_deleted_tarih_id_idx', ['firma_id', 'deleted_at', 'tarih', 'id']);
            $this->indexEkle($table, 'faturalar_firma_deleted_durum_tarih_id_idx', ['firma_id', 'deleted_at', 'durum', 'tarih', 'id']);
            $this->indexEkle($table, 'faturalar_firma_deleted_tur_tarih_id_idx', ['firma_id', 'deleted_at', 'tur', 'tarih', 'id']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('faturalar')) {
            return;
        }

        Schema::table('faturalar', function (Blueprint $table): void {
            $this->indexSil($table, 'faturalar_firma_deleted_tur_tarih_id_idx');
            $this->indexSil($table, 'faturalar_firma_deleted_durum_tarih_id_idx');
            $this->indexSil($table, 'faturalar_firma_deleted_tarih_id_idx');
        });
    }

    /**
     * @param  array<int, string>  $kolonlar
     */
    private function indexEkle(Blueprint $table, string $indexAdi, array $kolonlar): void
    {
        if (! $this->indexVarMi($indexAdi)) {
            $table->index($kolonlar, $indexAdi);
        }
    }

    private function indexSil(Blueprint $table, string $indexAdi): void
    {
        if ($this->indexVarMi($indexAdi)) {
            $table->dropIndex($indexAdi);
        }
    }

    private function indexVarMi(string $indexAdi): bool
    {
        $schema = Schema::getFacadeRoot();
        if (method_exists($schema, 'hasIndex')) {
            return Schema::hasIndex('faturalar', $indexAdi);
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            foreach (DB::select("PRAGMA index_list('faturalar')") as $index) {
                if (($index->name ?? null) === $indexAdi) {
                    return true;
                }
            }

            return false;
        }

        foreach (DB::select('SHOW INDEX FROM `faturalar` WHERE Key_name = ?', [$indexAdi]) as $index) {
            if (($index->Key_name ?? null) === $indexAdi) {
                return true;
            }
        }

        return false;
    }
};
