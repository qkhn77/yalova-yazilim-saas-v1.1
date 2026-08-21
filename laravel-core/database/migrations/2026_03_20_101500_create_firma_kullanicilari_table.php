<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firma_kullanicilari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('kullanici_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('rol_id')->nullable()->constrained('roller')->nullOnDelete();
            $table->enum('durum', ['aktif', 'pasif'])->default('aktif')->index();
            $table->boolean('varsayilan_firma_mi')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['firma_id', 'kullanici_id']);
            $table->index(['kullanici_id', 'durum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firma_kullanicilari');
    }
};

