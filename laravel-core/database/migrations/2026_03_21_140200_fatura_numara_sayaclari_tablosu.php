<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fatura_numara_sayaclari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->restrictOnDelete();
            $table->unsignedSmallInteger('yil');
            $table->unsignedInteger('son_sira')->default(0);
            $table->timestamps();

            $table->unique(['firma_id', 'yil']);
            $table->index(['firma_id', 'yil']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fatura_numara_sayaclari');
    }
};
