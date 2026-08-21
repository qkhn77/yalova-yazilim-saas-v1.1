<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stok_kartlari', function (Blueprint $table) {
            $table->foreignId('kategori_id')
                ->nullable()
                ->after('kategori_kodu')
                ->constrained('stok_kategorileri')
                ->nullOnDelete();
            $table->boolean('stok_takip')->default(true)->after('durum');
            $table->decimal('minimum_stok', 18, 4)->nullable()->after('kritik_seviye_miktar');
            $table->decimal('stok_miktari', 18, 4)->default(0)->after('minimum_stok');

            $table->index(['firma_id', 'kategori_id']);
            $table->index(['firma_id', 'stok_takip', 'durum']);
            $table->index(['firma_id', 'stok_miktari', 'minimum_stok']);
        });

        DB::table('stok_kartlari')
            ->whereNull('minimum_stok')
            ->update(['minimum_stok' => DB::raw('kritik_seviye_miktar')]);

        $hareketToplamlari = DB::table('stok_hareketleri')
            ->selectRaw("
                stok_id,
                SUM(
                    CASE
                        WHEN islem_turu IN ('alis', 'iade', 'satis_iadesi', 'transfer_giris') THEN miktar
                        WHEN islem_turu IN ('satis', 'alis_iadesi', 'transfer_cikis') THEN -miktar
                        ELSE 0
                    END
                ) AS net_miktar
            ")
            ->where('durum', 'aktif')
            ->groupBy('stok_id')
            ->get();

        foreach ($hareketToplamlari as $satir) {
            DB::table('stok_kartlari')
                ->where('id', (int) $satir->stok_id)
                ->update(['stok_miktari' => (string) $satir->net_miktar]);
        }
    }

    public function down(): void
    {
        Schema::table('stok_kartlari', function (Blueprint $table) {
            $table->dropIndex('stok_kartlari_firma_id_kategori_id_index');
            $table->dropIndex('stok_kartlari_firma_id_stok_takip_durum_index');
            $table->dropIndex('stok_kartlari_firma_id_stok_miktari_minimum_stok_index');
            $table->dropConstrainedForeignId('kategori_id');
            $table->dropColumn(['stok_takip', 'minimum_stok', 'stok_miktari']);
        });
    }
};
