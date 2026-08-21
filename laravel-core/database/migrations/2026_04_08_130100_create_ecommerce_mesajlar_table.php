<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_mesajlar', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('konu_id');
            $table->unsignedBigInteger('firma_id');
            $table->unsignedBigInteger('kullanici_id')->nullable();
            $table->string('gonderen_tipi', 32); // musteri | admin
            $table->boolean('ic_not_mu')->default(false);
            $table->text('icerik');
            $table->json('ekler')->nullable();
            $table->timestamp('okundu_at')->nullable();
            $table->timestamps();

            $table->index(['konu_id', 'created_at']);
            $table->index(['firma_id', 'gonderen_tipi']);
            $table->index(['firma_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_mesajlar');
    }
};