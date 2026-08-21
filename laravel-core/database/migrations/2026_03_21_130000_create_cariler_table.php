<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cariler', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->string('kod', 64);
            $table->string('ad');
            $table->string('kisa_ad', 128)->nullable();
            $table->string('tur', 32);
            $table->string('vergi_dairesi', 128)->nullable();
            $table->string('vergi_no', 32)->nullable();
            $table->string('telefon', 64)->nullable();
            $table->string('gsm', 64)->nullable();
            $table->string('email', 191)->nullable();
            $table->text('adres')->nullable();
            $table->string('il', 64)->nullable();
            $table->string('ilce', 64)->nullable();
            $table->string('yetkili_kisi', 191)->nullable();
            $table->decimal('risk_limiti', 18, 2)->default(0);
            $table->unsignedSmallInteger('vade_gunu')->default(0);
            $table->char('para_birimi', 3)->default('TRY');
            $table->text('aciklama')->nullable();
            $table->string('durum', 32)->default('aktif');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['firma_id', 'kod']);
            $table->index(['firma_id', 'tur']);
            $table->index(['firma_id', 'durum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cariler');
    }
};
