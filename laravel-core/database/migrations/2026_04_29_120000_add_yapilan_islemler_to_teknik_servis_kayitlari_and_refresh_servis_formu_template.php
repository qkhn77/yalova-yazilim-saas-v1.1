<?php

use App\TeknikServis\Servisler\TeknikServisBaskiSablonuServisi;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teknik_servis_kayitlari', function (Blueprint $table): void {
            if (! Schema::hasColumn('teknik_servis_kayitlari', 'yapilan_islemler')) {
                $table->text('yapilan_islemler')->nullable()->after('musteriye_gorunen_not');
            }
        });

        $hazirSablon = collect(app(TeknikServisBaskiSablonuServisi::class)->hazirSablonlar('servis_formu'))
            ->firstWhere('kod', 'teknik-servis-formu-a4');

        if (! is_array($hazirSablon)) {
            return;
        }

        DB::table('teknik_servis_baski_sablonlari')
            ->where('sablon_turu', 'servis_formu')
            ->where('kod', 'teknik-servis-formu-a4')
            ->update([
                'ad' => (string) ($hazirSablon['ad'] ?? 'Teknik Servis Formu'),
                'sayfa_tipi' => (string) ($hazirSablon['sayfa_tipi'] ?? 'a4'),
                'sablon_html' => (string) ($hazirSablon['sablon_html'] ?? ''),
                'sablon_css' => (string) ($hazirSablon['sablon_css'] ?? ''),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('teknik_servis_kayitlari', function (Blueprint $table): void {
            if (Schema::hasColumn('teknik_servis_kayitlari', 'yapilan_islemler')) {
                $table->dropColumn('yapilan_islemler');
            }
        });
    }
};
