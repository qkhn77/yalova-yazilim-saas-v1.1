<?php

use App\TeklifYonetimi\Servisler\TeklifBaskiSablonuServisi;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('firmalar') || ! Schema::hasTable('teklif_baski_sablonlari')) {
            return;
        }

        $hazirSablon = collect(app(TeklifBaskiSablonuServisi::class)->hazirSablonlar())
            ->firstWhere('kod', 'pc-teklif-a4');

        if (! is_array($hazirSablon)) {
            return;
        }

        $simdi = now();
        $firmaIds = DB::table('firmalar')->pluck('id');

        foreach ($firmaIds as $firmaId) {
            $mevcut = DB::table('teklif_baski_sablonlari')
                ->where('firma_id', (int) $firmaId)
                ->where('kod', 'pc-teklif-a4')
                ->first();

            $payload = [
                'ad' => (string) ($hazirSablon['ad'] ?? 'PC Teklif Şablonu A4'),
                'sayfa_tipi' => (string) ($hazirSablon['sayfa_tipi'] ?? 'a4'),
                'sablon_html' => (string) ($hazirSablon['sablon_html'] ?? ''),
                'sablon_css' => (string) ($hazirSablon['sablon_css'] ?? ''),
                'aktif' => true,
                'updated_at' => $simdi,
            ];

            if ($mevcut) {
                DB::table('teklif_baski_sablonlari')
                    ->where('id', $mevcut->id)
                    ->update($payload);

                continue;
            }

            DB::table('teklif_baski_sablonlari')->insert([
                'firma_id' => (int) $firmaId,
                'ad' => (string) ($hazirSablon['ad'] ?? 'PC Teklif Şablonu A4'),
                'kod' => 'pc-teklif-a4',
                'sayfa_tipi' => (string) ($hazirSablon['sayfa_tipi'] ?? 'a4'),
                'sablon_logo' => null,
                'sablon_html' => (string) ($hazirSablon['sablon_html'] ?? ''),
                'sablon_css' => (string) ($hazirSablon['sablon_css'] ?? ''),
                'varsayilan_mi' => false,
                'aktif' => true,
                'created_at' => $simdi,
                'updated_at' => $simdi,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('teklif_baski_sablonlari')) {
            return;
        }

        DB::table('teklif_baski_sablonlari')
            ->where('kod', 'pc-teklif-a4')
            ->delete();
    }
};
