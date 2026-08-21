<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_bildirim_sablonlari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->nullable()->constrained('firmalar')->nullOnDelete();
            $table->string('olay', 64);
            $table->string('kanal', 20);
            $table->string('locale', 12)->default('tr');
            $table->string('baslik', 255)->nullable();
            $table->text('icerik');
            $table->boolean('aktif_mi')->default(true);
            $table->timestamps();

            $table->unique(['firma_id', 'olay', 'kanal', 'locale'], 'ecom_bildirim_sablon_unique');
            $table->index(['firma_id', 'olay'], 'ecom_bildirim_sablon_firma_olay_idx');
            $table->index(['firma_id', 'kanal'], 'ecom_bildirim_sablon_firma_kanal_idx');
        });

        Schema::create('ecommerce_bildirim_loglari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->nullable()->constrained('firmalar')->nullOnDelete();
            $table->foreignId('siparis_id')->nullable()->constrained('siparisler')->nullOnDelete();
            $table->string('olay', 64);
            $table->string('kanal', 20);
            $table->string('locale', 12)->nullable();
            $table->string('hedef', 255)->nullable();
            $table->string('baslik', 255)->nullable();
            $table->text('icerik')->nullable();
            $table->string('durum', 24)->default('kuyrukta');
            $table->unsignedInteger('deneme_sayisi')->default(0);
            $table->text('hata')->nullable();
            $table->timestamp('gonderildi_at')->nullable();
            $table->timestamps();

            $table->index(['firma_id', 'olay', 'kanal'], 'ecom_bildirim_log_firma_olay_idx');
            $table->index(['siparis_id', 'olay', 'kanal'], 'ecom_bildirim_log_siparis_olay_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_bildirim_loglari');
        Schema::dropIfExists('ecommerce_bildirim_sablonlari');
    }
};

