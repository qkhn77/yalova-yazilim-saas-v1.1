<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kullanici_yetkileri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('kullanici_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('yetki_id')->constrained('yetkiler')->cascadeOnDelete();
            $table->enum('izin_tipi', ['ver', 'reddet'])->default('ver')->index();
            $table->timestamps();

            $table->unique(['firma_id', 'kullanici_id', 'yetki_id'], 'kullanici_yetkileri_unique');
            $table->index(['kullanici_id', 'izin_tipi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kullanici_yetkileri');
    }
};

