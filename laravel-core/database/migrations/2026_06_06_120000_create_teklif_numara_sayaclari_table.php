<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('teklif_numara_sayaclari')) {
            return;
        }

        Schema::create('teklif_numara_sayaclari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->restrictOnDelete();
            $table->unsignedSmallInteger('yil');
            $table->string('prefix', 16)->default('TKL');
            $table->unsignedInteger('son_sira')->default(0);
            $table->timestamps();

            $table->unique(['firma_id', 'yil', 'prefix'], 'teklif_numara_sayac_firma_yil_prefix_unique');
            $table->index(['firma_id', 'yil', 'prefix'], 'teklif_numara_sayac_firma_yil_prefix_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teklif_numara_sayaclari');
    }
};
