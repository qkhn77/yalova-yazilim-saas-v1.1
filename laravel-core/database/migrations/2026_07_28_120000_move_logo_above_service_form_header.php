<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

/* Logo üstte olacak şekilde servis formu başlığı */
.servis-ust {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}
.servis-ust > div { width: 100%; }
.servis-ust .logo-alani { order: -1; margin-bottom: 6px; }
CSS;

        $sorgu = DB::table('teknik_servis_baski_sablonlari')
            ->where('sablon_turu', 'kabul_formu')
            ->where('sablon_html', 'like', '%class="servis-ust"%')
            ->where(function ($query): void {
                $query->whereNull('sablon_css')
                    ->orWhere('sablon_css', 'not like', '%Logo üstte olacak şekilde servis formu başlığı%');
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

        $marker = '/* Logo üstte olacak şekilde servis formu başlığı */';

        DB::table('teknik_servis_baski_sablonlari')
            ->where('sablon_turu', 'kabul_formu')
            ->where('sablon_css', 'like', '%'.$marker.'%')
            ->get(['id', 'sablon_css'])
            ->each(function (object $sablon) use ($marker): void {
                $css = preg_replace('/\s*\/\* Logo üstte olacak şekilde servis formu başlığı \*\/.*$/s', '', (string) $sablon->sablon_css);

                DB::table('teknik_servis_baski_sablonlari')
                    ->where('id', $sablon->id)
                    ->update(['sablon_css' => $css, 'updated_at' => now()]);
            });
    }
};
