<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yetkiler', function (Blueprint $table): void {
            $table->id();
            $table->string('ad');
            $table->string('kod')->unique();
            $table->string('modul_kodu')->index();
            $table->enum('eylem', ['goruntule', 'olustur', 'guncelle', 'sil', 'onay', 'yonet'])->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yetkiler');
    }
};

