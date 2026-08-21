<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kasa_hesaplari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->string('kod', 64);
            $table->string('ad');
            $table->char('para_birimi', 3)->default('TRY');
            $table->string('sorumlu', 191)->nullable();
            $table->text('aciklama')->nullable();
            $table->string('durum', 32)->default('aktif');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['firma_id', 'kod']);
            $table->index(['firma_id', 'durum']);
        });

        Schema::create('banka_hesaplari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->string('kod', 64);
            $table->string('ad');
            $table->string('banka_adi', 191)->nullable();
            $table->string('sube', 128)->nullable();
            $table->string('hesap_no', 64)->nullable();
            $table->string('iban', 34)->nullable();
            $table->char('para_birimi', 3)->default('TRY');
            $table->text('aciklama')->nullable();
            $table->string('durum', 32)->default('aktif');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['firma_id', 'kod']);
            $table->index(['firma_id', 'durum']);
        });

        Schema::create('pos_hesaplari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->string('kod', 64);
            $table->string('ad');
            $table->string('terminal_no', 64)->nullable();
            $table->string('pos_saglayici', 128)->nullable();
            $table->char('para_birimi', 3)->default('TRY');
            $table->text('aciklama')->nullable();
            $table->string('durum', 32)->default('aktif');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['firma_id', 'kod']);
            $table->index(['firma_id', 'durum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_hesaplari');
        Schema::dropIfExists('banka_hesaplari');
        Schema::dropIfExists('kasa_hesaplari');
    }
};
