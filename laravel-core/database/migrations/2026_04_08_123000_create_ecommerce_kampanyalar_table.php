<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_kampanyalar', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->string('ad', 180);
            $table->string('tip', 32);
            $table->boolean('aktif_mi')->default(true);
            $table->boolean('kupon_gerekli')->default(false);
            $table->string('kupon_kodu', 64)->nullable();
            $table->date('baslangic_tarihi')->nullable();
            $table->date('bitis_tarihi')->nullable();
            $table->boolean('suresiz_mi')->default(false);
            $table->unsignedInteger('oncelik')->default(100);
            $table->boolean('birlesebilir_mi')->default(false);
            $table->decimal('indirim_orani', 8, 4)->nullable();
            $table->decimal('indirim_tutari', 12, 2)->nullable();
            $table->unsignedInteger('x_adet')->nullable();
            $table->unsignedInteger('y_adet')->nullable();
            $table->string('hedef_tipi', 32)->default('genel');
            $table->json('hedef_idler')->nullable();
            $table->unsignedInteger('kullanici_basi_limit')->nullable();
            $table->unsignedInteger('sistem_geneli_limit')->nullable();
            $table->unsignedInteger('kullanilan_adet')->default(0);
            $table->decimal('min_sepet_tutari', 12, 2)->nullable();
            $table->boolean('ucretsiz_kargo')->default(false);
            $table->string('para_birimi', 8)->nullable();
            $table->text('aciklama')->nullable();
            $table->json('kosullar')->nullable();
            $table->timestamps();

            $table->index(['firma_id', 'aktif_mi']);
            $table->index(['firma_id', 'tip']);
            $table->index(['firma_id', 'hedef_tipi']);
            $table->unique(['firma_id', 'kupon_kodu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_kampanyalar');
    }
};