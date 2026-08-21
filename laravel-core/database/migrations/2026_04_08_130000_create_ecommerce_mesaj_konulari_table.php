<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_mesaj_konulari', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->string('konu_tipi', 32); // musteri | urun
            $table->unsignedBigInteger('kullanici_id')->nullable();
            $table->unsignedBigInteger('stok_karti_id')->nullable();
            $table->string('baslik', 255);
            $table->string('durum', 32)->default('yeni');
            $table->boolean('okunmamis_mi')->default(true);
            $table->unsignedInteger('okunmamis_mesaj_sayisi')->default(1);
            $table->string('musteri_ad_soyad', 160)->nullable();
            $table->string('musteri_email', 255)->nullable();
            $table->string('musteri_telefon', 50)->nullable();
            $table->timestamp('son_musteri_mesaji_at')->nullable();
            $table->timestamp('son_admin_mesaji_at')->nullable();
            $table->timestamp('ilk_yanit_at')->nullable();
            $table->timestamp('sla_son_tarih_at')->nullable();
            $table->boolean('sla_ihlal_mi')->default(false);
            $table->timestamp('tamamlandi_at')->nullable();
            $table->timestamps();

            $table->index(['firma_id', 'konu_tipi']);
            $table->index(['firma_id', 'durum']);
            $table->index(['firma_id', 'okunmamis_mi']);
            $table->index(['firma_id', 'sla_ihlal_mi']);
            $table->index(['firma_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_mesaj_konulari');
    }
};