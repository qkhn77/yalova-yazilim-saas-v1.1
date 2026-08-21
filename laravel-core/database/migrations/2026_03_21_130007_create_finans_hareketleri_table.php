<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finans_hareketleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->string('tur', 32);
            $table->dateTime('tarih');
            $table->date('vade_tarihi')->nullable();
            $table->decimal('tutar', 18, 2);
            $table->char('para_birimi', 3)->default('TRY');
            $table->foreignId('cari_id')->nullable()->constrained('cariler')->nullOnDelete();
            $table->text('aciklama')->nullable();
            $table->string('referans_turu', 32)->nullable();
            $table->unsignedBigInteger('referans_id')->nullable();
            $table->string('durum', 32)->default('aktif');
            $table->foreignId('iptal_edilen_hareket_id')->nullable()->constrained('finans_hareketleri')->nullOnDelete();
            $table->timestamps();

            $table->index(['firma_id', 'tur']);
            $table->index(['firma_id', 'cari_id']);
            $table->index(['firma_id', 'tarih']);
            $table->index(['firma_id', 'referans_turu', 'referans_id']);
            $table->index(['firma_id', 'durum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finans_hareketleri');
    }
};
