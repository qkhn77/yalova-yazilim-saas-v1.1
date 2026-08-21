<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fatura_kalemleri')) {
            Schema::table('fatura_kalemleri', function (Blueprint $table): void {
                if (! Schema::hasColumn('fatura_kalemleri', 'kaynak_fatura_kalemi_id')) {
                    $table->unsignedBigInteger('kaynak_fatura_kalemi_id')->nullable()->after('fatura_id');
                    $table->index('kaynak_fatura_kalemi_id', 'fk_kalem_kaynak_idx');
                }
            });
        }

        if (Schema::hasTable('fatura_kalemi_olcu_dagilimlari')) {
            Schema::table('fatura_kalemi_olcu_dagilimlari', function (Blueprint $table): void {
                if (! Schema::hasColumn('fatura_kalemi_olcu_dagilimlari', 'kaynak_olcu_dagilimi_id')) {
                    $table->unsignedBigInteger('kaynak_olcu_dagilimi_id')->nullable()->after('fatura_kalemi_id');
                    $table->index('kaynak_olcu_dagilimi_id', 'fk_olcu_kaynak_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fatura_kalemi_olcu_dagilimlari') && Schema::hasColumn('fatura_kalemi_olcu_dagilimlari', 'kaynak_olcu_dagilimi_id')) {
            Schema::table('fatura_kalemi_olcu_dagilimlari', function (Blueprint $table): void {
                $table->dropIndex('fk_olcu_kaynak_idx');
                $table->dropColumn('kaynak_olcu_dagilimi_id');
            });
        }

        if (Schema::hasTable('fatura_kalemleri') && Schema::hasColumn('fatura_kalemleri', 'kaynak_fatura_kalemi_id')) {
            Schema::table('fatura_kalemleri', function (Blueprint $table): void {
                $table->dropIndex('fk_kalem_kaynak_idx');
                $table->dropColumn('kaynak_fatura_kalemi_id');
            });
        }
    }
};
