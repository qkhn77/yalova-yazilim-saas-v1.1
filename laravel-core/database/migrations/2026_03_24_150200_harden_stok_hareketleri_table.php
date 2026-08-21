<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stok_hareketleri', function (Blueprint $table) {
            $table->decimal('onceki_miktar', 18, 4)->default(0)->after('miktar');
            $table->decimal('sonraki_miktar', 18, 4)->default(0)->after('onceki_miktar');
            $table->decimal('birim_maliyet', 18, 2)->default(0)->after('birim_fiyat');
            $table->decimal('toplam_maliyet', 18, 2)->default(0)->after('toplam');
            $table->string('referans_tipi', 32)->nullable()->after('belge_turu');
            $table->unsignedBigInteger('referans_id')->nullable()->after('belge_id');
            $table->text('aciklama')->nullable()->after('durum');
            $table->dateTime('islem_tarihi')->nullable()->after('tarih');

            $table->index(['firma_id', 'islem_tarihi']);
            $table->index(['firma_id', 'referans_tipi', 'referans_id']);
        });

        DB::statement('UPDATE stok_hareketleri SET birim_maliyet = birim_fiyat, toplam_maliyet = toplam, referans_tipi = belge_turu, referans_id = belge_id, islem_tarihi = tarih');
    }

    public function down(): void
    {
        Schema::table('stok_hareketleri', function (Blueprint $table) {
            $table->dropIndex('stok_hareketleri_firma_id_islem_tarihi_index');
            $table->dropIndex('stok_hareketleri_firma_id_referans_tipi_referans_id_index');
            $table->dropColumn([
                'onceki_miktar',
                'sonraki_miktar',
                'birim_maliyet',
                'toplam_maliyet',
                'referans_tipi',
                'referans_id',
                'aciklama',
                'islem_tarihi',
            ]);
        });
    }
};
