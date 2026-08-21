<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Parti/lot ve fiziksel stok parçası katmanını test veritabanından
     * kaldırır. Ölçülü stok bundan sonra yalnız stok + ölçü + depo bazında
     * izlenir; seri no takibi bu işlemden etkilenmez.
     */
    public function up(): void
    {
        // Eski parti kapsamı, ölçü bakiyesi tekil indeksinin parçasıdır.
        // Kolondan önce indeksi kaldırıp ardından parçasız tekil indeksi kurarız.
        if (Schema::hasTable('stok_olcu_bakiyeleri') && (Schema::hasColumn('stok_olcu_bakiyeleri', 'parti_kapsami') || Schema::hasColumn('stok_olcu_bakiyeleri', 'parca_kapsami'))) {
            try {
                Schema::table('stok_olcu_bakiyeleri', function (Blueprint $blueprint): void {
                    $blueprint->dropUnique('stok_olcu_bakiye_tekil');
                });
            } catch (\Throwable) {
                // Yarım kalmış test geçişinde indeks daha önce kaldırılmış olabilir.
            }
        }

        foreach (['fatura_kalemi_olcu_dagilimlari', 'stok_hareketi_olcu_dagilimlari', 'stok_olcu_bakiyeleri', 'stok_hareketleri'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (['stok_partisi_id', 'stok_parcasi_id', 'parti_id'] as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                    $blueprint->dropConstrainedForeignId($column);
                });
            }

            foreach (['parti_kapsami', 'parca_kapsami'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                        $blueprint->dropColumn($column);
                    });
                }
            }
        }

        foreach (['stok_hareketi_partileri', 'stok_hareketi_parcalari', 'stok_parca_islem_loglari', 'stok_partileri', 'stok_parcalari', 'stok_parti_kimlikleri'] as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
            }
        }

        if (Schema::hasTable('stok_olcu_bakiyeleri')) {
            Schema::table('stok_olcu_bakiyeleri', function (Blueprint $blueprint): void {
                $blueprint->unique(['firma_id', 'stok_id', 'stok_olcusu_id', 'depo_id'], 'stok_olcu_bakiye_tekil');
            });
        }

        // Eski form alanları test verisinde kalmış olsa bile yeni işlemlerde
        // kullanılmaması için temizlenir.
        foreach (['fatura_kalemleri', 'siparis_kalemleri'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $degerler = [];
            foreach (['parti_no', 'parti_dagilimi', 'parca_kodu', 'parca_dagilimi'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $degerler[$column] = null;
                }
            }
            if ($degerler !== []) {
                DB::table($table)->update($degerler);
            }
        }
    }

    public function down(): void
    {
        // Fiziksel parça geçmişi test veritabanından bilerek kaldırıldığı için
        // otomatik geri yükleme yapılamaz.
    }
};
