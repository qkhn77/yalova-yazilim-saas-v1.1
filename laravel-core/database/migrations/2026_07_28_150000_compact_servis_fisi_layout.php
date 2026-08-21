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

        $css = <<<'CSS'

/* Servis fişi kompakt baskı düzeni */
.servis-ust { padding-bottom: 3px; margin-bottom: 4px; line-height: 1.15; }
.servis-ust .logo-alani { margin-bottom: 2px; }
.firma-ad { font-size: 11px; margin-bottom: 1px; }
.logo-alani img { max-height: 24px; margin-top: 1px; }
.belge-baslik { font-size: 10px; margin-bottom: 3px; }
.servis-bilgi-grid { gap: 1px; margin-bottom: 3px; }
.servis-blok { padding-top: 2px; margin-top: 3px; line-height: 1.2; }
.blok-baslik { margin-bottom: 1px; }
.servis-toplam { margin-top: 3px; padding-top: 2px; }
.imza-grid { gap: 5px; margin-top: 7px; }
.imza-kutu { padding-top: 2px; min-height: 14px; }
CSS;

        $sorgu = DB::table('teknik_servis_baski_sablonlari')
            ->where('sablon_turu', 'servis_fisi')
            ->whereIn('kod', ['servis-fisi-80mm', 'servis-fisi-58mm'])
            ->where(function ($query): void {
                $query->whereNull('sablon_css')
                    ->orWhere('sablon_css', 'not like', '%Servis fişi kompakt baskı düzeni%');
            })
            ;

        if (DB::getDriverName() === 'sqlite') {
            $sorgu->get(['id', 'sablon_css'])->each(function (object $sablon) use ($css): void {
                DB::table('teknik_servis_baski_sablonlari')
                    ->where('id', $sablon->id)
                    ->update([
                        'sablon_css' => (string) ($sablon->sablon_css ?? '').$css,
                        'updated_at' => now(),
                    ]);
            });
        } else {
            $sorgu->update([
                'sablon_css' => DB::raw("CONCAT(COALESCE(sablon_css, ''), ".DB::getPdo()->quote($css).")"),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('teknik_servis_baski_sablonlari')) {
            return;
        }

        $marker = '/* Servis fişi kompakt baskı düzeni */';

        DB::table('teknik_servis_baski_sablonlari')
            ->where('sablon_turu', 'servis_fisi')
            ->where('sablon_css', 'like', '%'.$marker.'%')
            ->get(['id', 'sablon_css'])
            ->each(function (object $sablon): void {
                $css = preg_replace('/\s*\/\* Servis fişi kompakt baskı düzeni \*\/.*$/s', '', (string) $sablon->sablon_css);

                DB::table('teknik_servis_baski_sablonlari')
                    ->whereKey($sablon->id)
                    ->update(['sablon_css' => $css, 'updated_at' => now()]);
            });
    }
};
