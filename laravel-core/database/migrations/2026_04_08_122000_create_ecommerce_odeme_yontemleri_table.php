<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_odeme_yontemleri', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->string('kod', 64);
            $table->string('ad', 160);
            $table->string('saglayici', 64);
            $table->boolean('aktif_mi')->default(true);
            $table->boolean('varsayilan_mi')->default(false);
            $table->boolean('uc_d_secure_zorunlu')->default(false);
            $table->boolean('taksit_aktif')->default(false);
            $table->unsignedTinyInteger('max_taksit')->nullable();
            $table->decimal('komisyon_orani', 8, 4)->nullable();
            $table->json('para_birimleri')->nullable();
            $table->boolean('iade_api_aktif')->default(true);
            $table->boolean('yeniden_deneme_aktif')->default(true);
            $table->unsignedTinyInteger('max_yeniden_deneme')->default(3);
            $table->string('webhook_dogrulama_anahtari', 255)->nullable();
            $table->json('saglayici_ayarlar')->nullable();
            $table->timestamps();

            $table->unique(['firma_id', 'kod']);
            $table->index(['firma_id', 'aktif_mi']);
            $table->index(['firma_id', 'saglayici']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_odeme_yontemleri');
    }
};