<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cari_hareketleri', function (Blueprint $table) {
            $table->unique('iptal_edilen_hareket_id', 'cari_hareketleri_iptal_edilen_unique');
        });

        Schema::table('stok_hareketleri', function (Blueprint $table) {
            $table->unique('iptal_edilen_hareket_id', 'stok_hareketleri_iptal_edilen_unique');
        });
    }

    public function down(): void
    {
        Schema::table('stok_hareketleri', function (Blueprint $table) {
            $table->dropUnique('stok_hareketleri_iptal_edilen_unique');
        });

        Schema::table('cari_hareketleri', function (Blueprint $table) {
            $table->dropUnique('cari_hareketleri_iptal_edilen_unique');
        });
    }
};
