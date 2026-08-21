<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hizli_satis_favorileri')) {
            return;
        }

        Schema::create('hizli_satis_favorileri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('kullanici_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('stok_karti_id')->constrained('stok_kartlari')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['firma_id', 'kullanici_id', 'stok_karti_id'], 'hizli_satis_favorileri_unique');
            $table->index(['firma_id', 'kullanici_id'], 'hizli_satis_favorileri_kullanici_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hizli_satis_favorileri');
    }
};
