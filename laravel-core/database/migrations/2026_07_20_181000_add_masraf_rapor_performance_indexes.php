<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('faturalar') && ! Schema::hasIndex('faturalar', 'fat_masraf_rapor_kaynak_idx')) {
            Schema::table('faturalar', function (Blueprint $table): void {
                $table->index(
                    ['firma_id', 'kaynak_tipi', 'tarih', 'durum'],
                    'fat_masraf_rapor_kaynak_idx'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('faturalar') && Schema::hasIndex('faturalar', 'fat_masraf_rapor_kaynak_idx')) {
            Schema::table('faturalar', function (Blueprint $table): void {
                $table->dropIndex('fat_masraf_rapor_kaynak_idx');
            });
        }
    }
};
