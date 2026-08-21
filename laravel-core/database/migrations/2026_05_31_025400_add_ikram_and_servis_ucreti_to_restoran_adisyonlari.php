<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restoran_adisyonlari', function (Blueprint $table): void {
            $table->decimal('ikram_toplam', 18, 2)->default(0)->after('indirim_toplam');
            $table->decimal('servis_ucreti', 18, 2)->default(0)->after('kdv_toplam');
        });

        Schema::table('restoran_adisyon_kalemleri', function (Blueprint $table): void {
            $table->boolean('ikram_mi')->default(false)->after('iskonto_tutari')->index();
            $table->decimal('ikram_tutari', 18, 2)->default(0)->after('ikram_mi');
        });
    }

    public function down(): void
    {
        Schema::table('restoran_adisyon_kalemleri', function (Blueprint $table): void {
            $table->dropColumn(['ikram_mi', 'ikram_tutari']);
        });

        Schema::table('restoran_adisyonlari', function (Blueprint $table): void {
            $table->dropColumn(['ikram_toplam', 'servis_ucreti']);
        });
    }
};
