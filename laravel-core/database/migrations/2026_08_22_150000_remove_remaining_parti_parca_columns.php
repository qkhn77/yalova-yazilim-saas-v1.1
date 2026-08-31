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
        // Eski kurulumlarda aynı özellikler farklı kolon adlarıyla oluşturulmuştu.
        // İlk cleanup migrationı uygulanmış veritabanlarında kalan son izleri kaldırır.
        foreach ([
            'siparis_kalemleri' => ['parca_kodu', 'parca_no', 'parca_dagilimi', 'parti_no', 'parti_dagilimi'],
            'muhasebe_barkodlu_satis_kalemleri' => ['parca_kodu', 'parca_no', 'parca_dagilimi', 'parti_no', 'parti_dagilimi'],
            'muhasebe_barkodlu_satis_iade_kalemleri' => ['parca_kodu', 'parca_no', 'parca_dagilimi', 'parti_no', 'parti_dagilimi'],
            'fatura_kalemleri' => ['parca_kodu', 'parca_no', 'parca_dagilimi', 'parti_no', 'parti_dagilimi'],
            'fatura_olcu_dagilimlari' => ['stok_parcasi_id'],
            'fatura_kalemi_olcu_dagilimlari' => ['stok_parcasi_id'],
        ] as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $existing = array_values(array_filter($columns, static fn (string $column): bool => Schema::hasColumn($table, $column)));
            if ($existing === []) {
                continue;
            }

            $foreignKeys = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)
                ? collect(DB::select(
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
    }

    public function down(): void
    {
        // Parti/parça alanları bilerek geri oluşturulmaz.
    }
};
