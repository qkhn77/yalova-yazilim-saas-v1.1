<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_hareketleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('stok_id')->constrained('stok_kartlari')->restrictOnDelete();
            $table->string('islem_turu', 32);
            $table->decimal('miktar', 18, 4);
            $table->decimal('birim_fiyat', 18, 2)->default(0);
            $table->decimal('toplam', 18, 2)->default(0);
            $table->string('belge_turu', 32);
            $table->unsignedBigInteger('belge_id');
            $table->dateTime('tarih');
            $table->string('durum', 32)->default('aktif');
            $table->foreignId('iptal_edilen_hareket_id')->nullable()->constrained('stok_hareketleri')->nullOnDelete();
            $table->timestamps();

            $table->index(['firma_id', 'stok_id']);
            $table->index(['firma_id', 'belge_turu', 'belge_id']);
            $table->index(['firma_id', 'tarih']);
            $table->index(['firma_id', 'durum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_hareketleri');
    }
};
