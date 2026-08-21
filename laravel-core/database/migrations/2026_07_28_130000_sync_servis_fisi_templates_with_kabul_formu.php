<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teknik_servis_baski_sablonlari')) {
            return;
        }

        $kaynaklar = DB::table('teknik_servis_baski_sablonlari')
            ->where('sablon_turu', 'kabul_formu')
            ->whereIn('kod', ['kabul_formu-80mm', 'kabul_formu-58mm'])
            ->get();

        foreach ($kaynaklar as $kaynak) {
            DB::table('teknik_servis_baski_sablonlari')
                ->where('firma_id', $kaynak->firma_id)
                ->where('sablon_turu', 'servis_fisi')
                ->where('kod', 'servis-fisi-'.$kaynak->sayfa_tipi)
                ->update([
                    'ad' => 'Servis Fişi '.$kaynak->sayfa_tipi,
                    'sayfa_tipi' => $kaynak->sayfa_tipi,
                    'sablon_logo' => $kaynak->sablon_logo,
                    'sablon_html' => $kaynak->sablon_html,
                    'sablon_css' => $kaynak->sablon_css,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Tasarımlar kabul formundan kopyalandığı için geri alma işlemi mevcut fiş tasarımını silmez.
    }
};
