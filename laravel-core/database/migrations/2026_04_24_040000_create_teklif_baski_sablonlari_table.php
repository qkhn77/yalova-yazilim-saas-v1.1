<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('teklif_baski_sablonlari')) {
            return;
        }

        Schema::create('teklif_baski_sablonlari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->string('ad');
            $table->string('kod', 64);
            $table->string('sayfa_tipi', 32)->default('a4');
            $table->string('sablon_logo')->nullable();
            $table->longText('sablon_html');
            $table->longText('sablon_css')->nullable();
            $table->boolean('varsayilan_mi')->default(false);
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->unique(['firma_id', 'kod'], 'teklif_baski_sablonlari_firma_kod_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teklif_baski_sablonlari');
    }
};
