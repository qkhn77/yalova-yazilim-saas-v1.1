<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cari_hareket_eslesmeleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('borc_hareket_id')->constrained('cari_hareketleri')->restrictOnDelete();
            $table->foreignId('alacak_hareket_id')->constrained('cari_hareketleri')->restrictOnDelete();
            $table->decimal('eslesen_tutar', 18, 2);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['firma_id', 'borc_hareket_id']);
            $table->index(['firma_id', 'alacak_hareket_id']);
            $table->index(['firma_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cari_hareket_eslesmeleri');
    }
};
