<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faturalar', function (Blueprint $table) {
            $table->decimal('genel_indirim_tutari', 18, 2)->default(0);
            $table->boolean('kdv_dahil_fiyatlandirma_mi')->default(false);
            $table->foreignId('bagli_fatura_id')->nullable()->constrained('faturalar')->nullOnDelete();
        });

        Schema::table('fatura_kalemleri', function (Blueprint $table) {
            $table->decimal('satir_indirim_tutari', 18, 2)->default(0);
            $table->decimal('net_tutar', 18, 2)->default(0);
            $table->decimal('kdv_tutari', 18, 2)->default(0);
        });

        if (Schema::hasTable('faturalar')) {
            DB::table('faturalar')->where('tur', 'iade')->update(['tur' => 'satis_iadesi']);
        }
    }

    public function down(): void
    {
        Schema::table('fatura_kalemleri', function (Blueprint $table) {
            $table->dropColumn(['satir_indirim_tutari', 'net_tutar', 'kdv_tutari']);
        });

        Schema::table('faturalar', function (Blueprint $table) {
            $table->dropForeign(['bagli_fatura_id']);
            $table->dropColumn(['genel_indirim_tutari', 'kdv_dahil_fiyatlandirma_mi', 'bagli_fatura_id']);
        });
    }
};
