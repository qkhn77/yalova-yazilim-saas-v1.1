<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_kullanici_adresleri', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->unsignedBigInteger('kullanici_id');
            $table->string('baslik', 80);
            $table->string('ad_soyad', 160);
            $table->string('telefon', 32);
            $table->string('ulke_kodu', 2)->default('TR');
            $table->string('sehir', 80);
            $table->string('ilce', 80);
            $table->string('mahalle', 120)->nullable();
            $table->string('posta_kodu', 20)->nullable();
            $table->text('acik_adres');
            $table->string('adres_notu', 500)->nullable();
            $table->boolean('varsayilan_teslimat_mi')->default(false);
            $table->boolean('varsayilan_fatura_mi')->default(false);
            $table->timestamps();

            $table->index(['firma_id', 'kullanici_id']);
            $table->index(['firma_id', 'kullanici_id', 'varsayilan_teslimat_mi'], 'ecom_adres_teslimat_idx');
            $table->index(['firma_id', 'kullanici_id', 'varsayilan_fatura_mi'], 'ecom_adres_fatura_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_kullanici_adresleri');
    }
};
