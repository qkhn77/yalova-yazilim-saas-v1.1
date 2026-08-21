<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kasa_hesaplari', function (Blueprint $table): void {
            $table->index(['firma_id', 'created_at'], 'kasa_hesaplari_firma_created_idx');
        });

        Schema::table('kasa_hareketleri', function (Blueprint $table): void {
            $table->index(['firma_id', 'kasa_hesap_id', 'durum'], 'kasa_hareketleri_firma_hesap_durum_idx');
        });
    }

    public function down(): void
    {
        Schema::table('kasa_hareketleri', function (Blueprint $table): void {
            $table->dropIndex('kasa_hareketleri_firma_hesap_durum_idx');
        });

        Schema::table('kasa_hesaplari', function (Blueprint $table): void {
            $table->dropIndex('kasa_hesaplari_firma_created_idx');
        });
    }
};
