<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stok_kartlari', function (Blueprint $table) {
            $table->decimal('guncel_birim_maliyet', 18, 2)->default(0)->after('stok_miktari');
            $table->decimal('stok_degeri', 18, 2)->default(0)->after('guncel_birim_maliyet');
            $table->decimal('son_giris_maliyeti', 18, 2)->nullable()->after('stok_degeri');
            $table->dateTime('son_hareket_tarihi')->nullable()->after('son_giris_maliyeti');

            $table->index(['firma_id', 'stok_degeri']);
            $table->index(['firma_id', 'guncel_birim_maliyet']);
        });
    }

    public function down(): void
    {
        Schema::table('stok_kartlari', function (Blueprint $table) {
            $table->dropIndex('stok_kartlari_firma_id_stok_degeri_index');
            $table->dropIndex('stok_kartlari_firma_id_guncel_birim_maliyet_index');
            $table->dropColumn([
                'guncel_birim_maliyet',
                'stok_degeri',
                'son_giris_maliyeti',
                'son_hareket_tarihi',
            ]);
        });
    }
};
