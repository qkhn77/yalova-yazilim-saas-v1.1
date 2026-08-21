<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siparisler', function (Blueprint $table): void {
            $table->timestamp('odeme_suresi_bitis_at')->nullable()->after('stok_dusuldu_mi');
            $table->unsignedSmallInteger('odeme_deneme_sayisi')->default(0)->after('odeme_suresi_bitis_at');
        });

        Schema::table('stok_kartlari', function (Blueprint $table): void {
            $table->decimal('rezerve_miktar', 12, 4)->default(0)->after('stok_miktari');
        });

        Schema::table('siparis_kalemleri', function (Blueprint $table): void {
            $table->decimal('stok_rezerv_miktari', 12, 4)->default(0)->after('miktar');
        });
    }

    public function down(): void
    {
        Schema::table('siparis_kalemleri', function (Blueprint $table): void {
            $table->dropColumn('stok_rezerv_miktari');
        });

        Schema::table('stok_kartlari', function (Blueprint $table): void {
            $table->dropColumn('rezerve_miktar');
        });

        Schema::table('siparisler', function (Blueprint $table): void {
            $table->dropColumn(['odeme_suresi_bitis_at', 'odeme_deneme_sayisi']);
        });
    }
};
