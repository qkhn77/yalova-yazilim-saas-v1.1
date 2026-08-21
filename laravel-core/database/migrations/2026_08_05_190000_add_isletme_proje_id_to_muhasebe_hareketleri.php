<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['faturalar', 'cari_hareketleri', 'finans_hareketleri'] as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'isletme_proje_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->foreignId('isletme_proje_id')
                    ->nullable()
                    ->after('firma_id')
                    ->constrained('isletme_projeleri')
                    ->nullOnDelete();
                $table->index(['firma_id', 'isletme_proje_id', 'created_at'], $tableName.'_proje_created_idx');
            });
        }
    }

    public function down(): void
    {
        foreach (['faturalar', 'cari_hareketleri', 'finans_hareketleri'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'isletme_proje_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropForeign(['isletme_proje_id']);
                $table->dropIndex($tableName.'_proje_created_idx');
                $table->dropColumn('isletme_proje_id');
            });
        }
    }
};
