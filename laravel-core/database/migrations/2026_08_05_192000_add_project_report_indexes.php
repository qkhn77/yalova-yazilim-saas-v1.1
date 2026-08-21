<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('finans_hareketleri') && Schema::hasColumn('finans_hareketleri', 'isletme_proje_id')) {
            Schema::table('finans_hareketleri', function (Blueprint $table): void {
                $table->index(['firma_id', 'isletme_proje_id', 'durum', 'tarih'], 'finans_proje_durum_tarih_idx');
            });
        }

        if (Schema::hasTable('cari_hareketleri') && Schema::hasColumn('cari_hareketleri', 'isletme_proje_id')) {
            Schema::table('cari_hareketleri', function (Blueprint $table): void {
                $table->index(['firma_id', 'isletme_proje_id', 'durum', 'islem_tarihi'], 'cari_proje_durum_tarih_idx');
            });
        }

        if (Schema::hasTable('faturalar') && Schema::hasColumn('faturalar', 'isletme_proje_id')) {
            Schema::table('faturalar', function (Blueprint $table): void {
                $table->index(['firma_id', 'isletme_proje_id', 'durum', 'tarih'], 'fatura_proje_durum_tarih_idx');
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'finans_hareketleri' => 'finans_proje_durum_tarih_idx',
            'cari_hareketleri' => 'cari_proje_durum_tarih_idx',
            'faturalar' => 'fatura_proje_durum_tarih_idx',
        ] as $tableName => $indexName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                    $table->dropIndex($indexName);
                });
            }
        }
    }
};
