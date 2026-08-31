<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kur_farki_hareketleri')) {
            return;
        }

        Schema::create('kur_farki_hareketleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('fatura_id')->constrained('faturalar')->cascadeOnDelete();
            $table->foreignId('finans_hareket_id')->constrained('finans_hareketleri')->cascadeOnDelete();
            $table->foreignId('fatura_finans_kapama_id')->unique()->constrained('fatura_finans_kapatmalari')->cascadeOnDelete();
            $table->decimal('tutar', 18, 8);
            $table->string('yon', 16);
            $table->char('para_birimi', 3)->default('TRY');
            $table->string('durum', 16)->default('aktif');
            $table->text('aciklama')->nullable();
            $table->timestamps();

            $table->index(['firma_id', 'created_at']);
            $table->index(['firma_id', 'yon', 'durum']);
            $table->index(['firma_id', 'fatura_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kur_farki_hareketleri');
    }
};
