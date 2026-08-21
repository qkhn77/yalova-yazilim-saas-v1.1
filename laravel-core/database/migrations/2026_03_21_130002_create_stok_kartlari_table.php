<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_kartlari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->string('kod', 64);
            $table->string('ad');
            $table->string('kisa_ad', 128)->nullable();
            $table->string('barkod', 128)->nullable();
            $table->string('tur', 32);
            $table->string('kategori_kodu', 64)->nullable();
            $table->string('birim', 32)->default('AD');
            $table->decimal('alis_fiyati', 18, 2)->default(0);
            $table->decimal('satis_fiyati', 18, 2)->default(0);
            $table->char('para_birimi', 3)->default('TRY');
            $table->decimal('kdv_orani', 5, 2)->default(0);
            $table->decimal('kritik_seviye_miktar', 18, 4)->default(0);
            $table->text('aciklama')->nullable();
            $table->string('durum', 32)->default('aktif');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['firma_id', 'kod']);
            $table->index(['firma_id', 'barkod']);
            $table->index(['firma_id', 'tur']);
            $table->index(['firma_id', 'durum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_kartlari');
    }
};
