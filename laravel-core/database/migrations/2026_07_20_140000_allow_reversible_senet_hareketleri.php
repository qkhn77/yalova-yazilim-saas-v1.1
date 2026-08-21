<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('senet_hareketleri', function (Blueprint $table): void {
            // senet_id_foreign, mevcut bileşik unique indeksin ilk kolonunu
            // kullandığı için unique indeks kaldırılmadan önce bağımsız indeks gerekir.
            $table->index(['senet_id'], 'senet_hareketleri_senet_id_index');
            $table->dropUnique('senet_hareketleri_senet_turu_unique');
            $table->index(['senet_id', 'islem_turu'], 'senet_hareketleri_senet_turu_index');
        });
    }

    public function down(): void
    {
        Schema::table('senet_hareketleri', function (Blueprint $table): void {
            $table->dropIndex('senet_hareketleri_senet_turu_index');
            $table->dropIndex('senet_hareketleri_senet_id_index');
            $table->unique(['senet_id', 'islem_turu'], 'senet_hareketleri_senet_turu_unique');
        });
    }
};
