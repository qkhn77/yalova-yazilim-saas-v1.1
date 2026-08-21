<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fatura_kalemleri') && Schema::hasColumn('fatura_kalemleri', 'kaynak_fatura_kalemi_id')) {
            Schema::table('fatura_kalemleri', function (Blueprint $table): void {
                $table->foreign('kaynak_fatura_kalemi_id', 'fk_fkalem_kaynak_fk')
                    ->references('id')->on('fatura_kalemleri')->restrictOnDelete();
            });
        }

        if (Schema::hasTable('fatura_kalemi_olcu_dagilimlari') && Schema::hasColumn('fatura_kalemi_olcu_dagilimlari', 'kaynak_olcu_dagilimi_id')) {
            Schema::table('fatura_kalemi_olcu_dagilimlari', function (Blueprint $table): void {
                $table->foreign('kaynak_olcu_dagilimi_id', 'fk_fodag_kaynak_fk')
                    ->references('id')->on('fatura_kalemi_olcu_dagilimlari')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fatura_kalemi_olcu_dagilimlari')) {
            Schema::table('fatura_kalemi_olcu_dagilimlari', function (Blueprint $table): void {
                $table->dropForeign('fk_fodag_kaynak_fk');
            });
        }

        if (Schema::hasTable('fatura_kalemleri')) {
            Schema::table('fatura_kalemleri', function (Blueprint $table): void {
                $table->dropForeign('fk_fkalem_kaynak_fk');
            });
        }
    }
};
