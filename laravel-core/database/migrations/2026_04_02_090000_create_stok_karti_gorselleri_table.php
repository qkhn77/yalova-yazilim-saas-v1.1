<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_karti_gorselleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stok_karti_id')->constrained('stok_kartlari')->cascadeOnDelete();
            $table->string('dosya_yolu');
            $table->string('alt_metin')->nullable();
            $table->unsignedInteger('sira')->default(0);
            $table->boolean('kapak_mi')->default(false);
            $table->boolean('aktif_mi')->default(true);
            $table->timestamps();

            $table->index(['stok_karti_id', 'aktif_mi']);
            $table->index(['stok_karti_id', 'sira']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_karti_gorselleri');
    }
};
