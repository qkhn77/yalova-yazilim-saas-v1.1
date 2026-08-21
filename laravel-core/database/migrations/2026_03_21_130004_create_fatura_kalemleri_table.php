<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fatura_kalemleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fatura_id')->constrained('faturalar')->cascadeOnDelete();
            $table->foreignId('stok_id')->nullable()->constrained('stok_kartlari')->nullOnDelete();
            $table->boolean('hizmet_mi')->default(false);
            $table->text('aciklama')->nullable();
            $table->decimal('miktar', 18, 4)->default(0);
            $table->decimal('birim_fiyat', 18, 2)->default(0);
            $table->decimal('kdv_orani', 5, 2)->default(0);
            $table->decimal('toplam', 18, 2)->default(0);
            $table->timestamps();

            $table->index('fatura_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fatura_kalemleri');
    }
};
