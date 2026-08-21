<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('isletme_proje_kullanicilari')) {
            return;
        }

        Schema::create('isletme_proje_kullanicilari', function (Blueprint $table): void {
            $table->foreignId('isletme_proje_id')->constrained('isletme_projeleri')->cascadeOnDelete();
            $table->foreignId('kullanici_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['isletme_proje_id', 'kullanici_id']);
            $table->index('kullanici_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('isletme_proje_kullanicilari');
    }
};
