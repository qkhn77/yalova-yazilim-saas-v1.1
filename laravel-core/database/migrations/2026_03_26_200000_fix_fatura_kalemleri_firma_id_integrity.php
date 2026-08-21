<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fatura_kalemleri') || ! Schema::hasTable('faturalar')) {
            return;
        }

        if (! Schema::hasColumn('fatura_kalemleri', 'firma_id')) {
            return;
        }

        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('UPDATE fatura_kalemleri fk INNER JOIN faturalar f ON f.id = fk.fatura_id SET fk.firma_id = f.firma_id WHERE fk.firma_id IS NULL');
        } elseif ($driver === 'sqlite') {
            DB::statement('UPDATE fatura_kalemleri SET firma_id = (SELECT firma_id FROM faturalar WHERE faturalar.id = fatura_kalemleri.fatura_id) WHERE firma_id IS NULL');
        } else {
            DB::statement('UPDATE fatura_kalemleri SET firma_id = (SELECT firma_id FROM faturalar WHERE faturalar.id = fatura_kalemleri.fatura_id) WHERE firma_id IS NULL');
        }

        // Bu tabloda firma_id foreign key kuralı SET NULL olduğu için nullable kalmalıdır.
        // Burada sadece null kayıtları doldurup mevcut kısıtlarla uyumlu şekilde devam ediyoruz.
    }

    public function down(): void
    {
        if (! Schema::hasTable('fatura_kalemleri') || ! Schema::hasColumn('fatura_kalemleri', 'firma_id')) {
            return;
        }

        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE fatura_kalemleri MODIFY firma_id BIGINT UNSIGNED NULL');
        }
    }
};
