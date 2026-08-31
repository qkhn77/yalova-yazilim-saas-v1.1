<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Parça kapsamı, eski ölçü bakiyesi tekil indeksinin parçasıydı.
        // Kolonu kaldırmadan önce indeksi kaldırmak farklı şema sürümlerinde
        // migration'ın yarıda kalmasını önler.
        if (Schema::hasTable('stok_olcu_bakiyeleri')) {
            $driver = Schema::getConnection()->getDriverName();
            $indexExists = in_array($driver, ['mysql', 'mariadb'], true)
                && Schema::getConnection()->table('information_schema.statistics')
                    ->whereRaw('table_schema = database()')
                    ->where('table_name', 'stok_olcu_bakiyeleri')
                    ->where('index_name', 'stok_olcu_bakiye_tekil')
                    ->exists();

            if ($indexExists) {
                Schema::table('stok_olcu_bakiyeleri', function (Blueprint $table): void {
                    $table->dropUnique('stok_olcu_bakiye_tekil');
                });
            }
        }

        // Parti/parça bağlantısı taşıyan belge alanları korunmuş belgelerin
        // kendisini silmeden kaldırılır. Seri ve ölçü alanlarına dokunulmaz.
        foreach ([
            'siparis_kalemleri' => ['parca_kodu', 'parca_dagilimi'],
            'muhasebe_barkodlu_satis_kalemleri' => ['parca_kodu', 'parca_dagilimi'],
            'muhasebe_barkodlu_satis_iade_kalemleri' => ['parca_kodu', 'parca_no', 'parca_dagilimi', 'parti_no', 'parti_dagilimi'],
            'siparis_kalemleri' => ['parca_kodu', 'parca_no', 'parca_dagilimi', 'parti_no', 'parti_dagilimi'],
            'fatura_kalemleri' => ['parca_kodu', 'parca_no', 'parca_dagilimi', 'parti_no', 'parti_dagilimi'],
            'fatura_olcu_dagilimlari' => ['stok_parcasi_id'],
            'fatura_kalemi_olcu_dagilimlari' => ['stok_parcasi_id'],
            'stok_olcu_bakiyeleri' => ['stok_parcasi_id', 'parca_kapsami'],
            'stok_hareketleri' => ['stok_partisi_id', 'stok_parcasi_id', 'parti_id', 'parti_no', 'parti_dagilimi', 'parca_kodu', 'parca_dagilimi'],
        ] as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $existing = array_values(array_filter($columns, static fn (string $column): bool => Schema::hasColumn($table, $column)));
            if ($existing === []) {
                continue;
            }

            $foreignKeys = in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)
                ? collect(Schema::getConnection()->select(
                    'select distinct constraint_name from information_schema.key_column_usage where table_schema = database() and table_name = ? and column_name in ('.implode(',', array_fill(0, count($existing), '?')).') and referenced_table_name is not null',
                    [$table, ...$existing],
                ))
                    ->pluck('constraint_name')
                    ->filter()
                    ->values()
                    ->all()
                : [];

            Schema::table($table, function (Blueprint $blueprint) use ($existing, $foreignKeys): void {
                foreach ($foreignKeys as $foreignKey) {
                    $blueprint->dropForeign((string) $foreignKey);
                }
                $blueprint->dropColumn($existing);
            });
        }

        if (Schema::hasTable('stok_kartlari') && Schema::hasColumn('stok_kartlari', 'stok_takip_tipi')) {
            // Alan seri takibi için gereklidir; yalnızca parti kayıtları basit takibe alınır.
            DB::table('stok_kartlari')
                ->where('stok_takip_tipi', 'parti')
                ->update(['stok_takip_tipi' => 'basit', 'updated_at' => now()]);
        }

        foreach ([
            'stok_hareketi_partileri',
            'stok_hareketi_parcalari',
            'stok_parca_islem_loglari',
            'stok_parti_kimlikleri',
            'stok_partileri',
            'stok_parcalari',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        // Parti/parça tabloları ve geçmiş verisi bilerek geri oluşturulmaz.
    }
};
