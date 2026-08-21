<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stok_barkodlari') || ! Schema::hasTable('stok_kartlari')) {
            return;
        }

        $kayitlar = DB::table('stok_kartlari')
            ->select(['id', 'firma_id', 'barkod'])
            ->whereNotNull('barkod')
            ->whereRaw("TRIM(COALESCE(barkod, '')) <> ''")
            ->get();

        foreach ($kayitlar as $kayit) {
            $varMi = DB::table('stok_barkodlari')
                ->where('firma_id', (int) $kayit->firma_id)
                ->where('barkod', (string) $kayit->barkod)
                ->exists();

            if ($varMi) {
                continue;
            }

            DB::table('stok_barkodlari')->insert([
                'firma_id' => (int) $kayit->firma_id,
                'stok_id' => (int) $kayit->id,
                'barkod' => (string) $kayit->barkod,
                'varsayilan_mi' => true,
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Backfill verisini geri sarmak guvenli degil; no-op.
    }
};

