<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stok_barkodlari')) {
            return;
        }

        Schema::create('stok_barkodlari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('stok_id')->constrained('stok_kartlari')->cascadeOnDelete();
            $table->string('barkod', 128);
            $table->boolean('varsayilan_mi')->default(false);
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->unique(['firma_id', 'barkod'], 'stok_barkodlari_firma_barkod_uq');
            $table->index(['firma_id', 'stok_id'], 'stok_barkodlari_firma_stok_idx');
            $table->index(['firma_id', 'aktif'], 'stok_barkodlari_firma_aktif_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_barkodlari');
    }
};

