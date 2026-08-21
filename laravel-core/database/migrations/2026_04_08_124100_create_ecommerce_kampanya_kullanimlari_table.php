<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_kampanya_kullanimlari', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->unsignedBigInteger('kampanya_id');
            $table->unsignedBigInteger('kullanici_id')->nullable();
            $table->unsignedBigInteger('siparis_id')->nullable();
            $table->unsignedInteger('adet')->default(1);
            $table->timestamps();

            $table->index(['firma_id', 'kampanya_id']);
            $table->index(['kampanya_id', 'kullanici_id']);
            $table->index(['kampanya_id', 'siparis_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_kampanya_kullanimlari');
    }
};