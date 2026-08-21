<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Parti/lot canlı işlevini kaldırır. Geçmiş stok hareketleri ve parti
     * satırları denetim izi olduğu için silinmez; yalnız yeni işlemlerde
     * kullanılmaması için kartlar basit stok takibine alınır.
     */
    public function up(): void
    {
        if (Schema::hasTable('stok_kartlari')) {
            DB::table('stok_kartlari')
                ->where('stok_takip_tipi', 'parti')
                ->update(['stok_takip_tipi' => 'basit', 'updated_at' => now()]);
        }

        if (Schema::hasTable('yetkiler')) {
            $yetkiIds = DB::table('yetkiler')
                ->whereIn('kod', ['stok_parti.goruntule', 'stok_parti.duzelt'])
                ->pluck('id');

            if ($yetkiIds->isNotEmpty() && Schema::hasTable('rol_yetkileri')) {
                DB::table('rol_yetkileri')->whereIn('yetki_id', $yetkiIds)->delete();
            }

            DB::table('yetkiler')->whereIn('id', $yetkiIds)->delete();
        }
    }

    public function down(): void
    {
        // Eski parti kartlarının hangi tarihte basite dönüştürüldüğü ayrıca
        // tutulmadığından geri dönüşte otomatik tip geri yüklenmez.
    }
};
