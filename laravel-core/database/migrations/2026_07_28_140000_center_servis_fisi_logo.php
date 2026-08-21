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

/* Servis fişi logosu üstte ve ortada */
.servis-ust .logo-alani {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    text-align: center;
}
.servis-ust .logo-alani img {
    display: block;
    margin-left: auto;
    margin-right: auto;
}
CSS;

        $sorgu = DB::table('teknik_servis_baski_sablonlari')
            ->where('sablon_turu', 'servis_fisi')
            ->whereIn('kod', ['servis-fisi-80mm', 'servis-fisi-58mm'])
            ->where(function ($query): void {
                $query->whereNull('sablon_css')
                    ->orWhere('sablon_css', 'not like', '%Servis fişi logosu üstte ve ortada%');
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

        $marker = '/* Servis fişi logosu üstte ve ortada */';

        DB::table('teknik_servis_baski_sablonlari')
            ->where('sablon_turu', 'servis_fisi')
            ->where('sablon_css', 'like', '%'.$marker.'%')
            ->get(['id', 'sablon_css'])
            ->each(function (object $sablon) use ($marker): void {
                $css = preg_replace('/\s*\/\* Servis fişi logosu üstte ve ortada \*\/.*$/s', '', (string) $sablon->sablon_css);

                DB::table('teknik_servis_baski_sablonlari')
                    ->where('id', $sablon->id)
                    ->update(['sablon_css' => $css, 'updated_at' => now()]);
            });
    }
};
