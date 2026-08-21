<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_kargo_yontemleri', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->string('ad', 160);
            $table->string('tip', 32)->default('sabit');
            $table->boolean('aktif_mi')->default(true);
            $table->decimal('sabit_ucret', 12, 2)->nullable();
            $table->decimal('ucretsiz_esik', 12, 2)->nullable();
            $table->unsignedInteger('tahmini_teslim_gun')->nullable();
            $table->boolean('entegrasyon_aktif')->default(false);
            $table->string('entegrasyon', 64)->nullable();
            $table->json('entegrasyon_ayarlar')->nullable();
            $table->json('kural')->nullable();
            $table->json('bolge_kurali')->nullable();
            $table->boolean('iade_kargo_aktif')->default(false);
            $table->json('iade_kargo_ayarlar')->nullable();
            $table->timestamps();

            $table->index(['firma_id', 'aktif_mi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_kargo_yontemleri');
    }
};