<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faturalar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('cari_id')->nullable()->constrained('cariler')->nullOnDelete();
            $table->string('tur', 32);
            $table->string('durum', 32)->default('taslak');
            $table->string('fatura_no', 64)->nullable();
            $table->dateTime('tarih');
            $table->date('vade_tarihi')->nullable();
            $table->decimal('ara_toplam', 18, 2)->default(0);
            $table->decimal('kdv_toplam', 18, 2)->default(0);
            $table->decimal('genel_toplam', 18, 2)->default(0);
            $table->char('para_birimi', 3)->default('TRY');
            $table->text('aciklama')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['firma_id', 'tur']);
            $table->index(['firma_id', 'durum']);
            $table->index(['firma_id', 'tarih']);
            $table->unique(['firma_id', 'fatura_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faturalar');
    }
};
