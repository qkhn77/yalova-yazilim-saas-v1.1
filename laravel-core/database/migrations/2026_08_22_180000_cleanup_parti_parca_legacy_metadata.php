<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('firma_ayarlari')) {
            DB::table('firma_ayarlari')
                ->whereIn('anahtar', ['stok_parti_telegram_aktif_mi', 'stok_parti_telegram_uyari_gun'])
                ->delete();
        }

        if (! Schema::hasTable('yetkiler')) {
            return;
        }

        $yetkiIdleri = DB::table('yetkiler')
            ->whereIn('kod', ['stok_parti.goruntule', 'stok_parti.duzelt'])
            ->pluck('id');

        if ($yetkiIdleri->isEmpty()) {
            return;
        }

        foreach (['rol_yetkileri', 'kullanici_yetkileri'] as $pivot) {
            if (Schema::hasTable($pivot)) {
                DB::table($pivot)->whereIn('yetki_id', $yetkiIdleri)->delete();
            }
        }

        DB::table('yetkiler')->whereIn('id', $yetkiIdleri)->delete();
    }

    public function down(): void
    {
        // Legacy parti/parça metadata is intentionally not restored.
    }
};
