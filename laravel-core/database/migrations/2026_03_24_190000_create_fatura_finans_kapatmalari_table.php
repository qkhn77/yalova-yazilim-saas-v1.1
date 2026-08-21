<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fatura_finans_kapatmalari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('fatura_id')->constrained('faturalar')->cascadeOnDelete();
            $table->foreignId('finans_hareket_id')->constrained('finans_hareketleri')->cascadeOnDelete();
            $table->decimal('uygulanan_tutar', 18, 2);
            $table->char('para_birimi', 3)->default('TRY');
            $table->timestamps();

            $table->unique(['finans_hareket_id', 'fatura_id'], 'fatura_finans_kapatmalari_finans_fatura_unique');
            $table->index(['firma_id', 'fatura_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fatura_finans_kapatmalari');
    }
};
