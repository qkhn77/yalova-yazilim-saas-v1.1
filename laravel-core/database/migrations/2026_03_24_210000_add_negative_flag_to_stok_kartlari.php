<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stok_kartlari', function (Blueprint $table) {
            $table->boolean('negative_flag')->default(false)->after('son_hareket_tarihi');
            $table->index(['firma_id', 'negative_flag']);
        });
    }

    public function down(): void
    {
        Schema::table('stok_kartlari', function (Blueprint $table) {
            $table->dropIndex('stok_kartlari_firma_id_negative_flag_index');
            $table->dropColumn('negative_flag');
        });
    }
};
