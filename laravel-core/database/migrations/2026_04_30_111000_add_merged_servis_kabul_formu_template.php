<?php

use App\TeknikServis\Servisler\TeknikServisBaskiSablonuServisi;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('firmalar') || ! Schema::hasTable('teknik_servis_baski_sablonlari')) {
            return;
        }

        $hazirSablon = collect(app(TeknikServisBaskiSablonuServisi::class)->hazirSablonlar('servis_formu'))
            ->firstWhere('kod', 'yalova-bilgisayar-servis-kabul-formu-a4');

        if (! is_array($hazirSablon)) {
            return;
        }

        $firmaIds = DB::table('firmalar')->pluck('id');
        $simdi = now();

        foreach ($firmaIds as $firmaId) {
            $mevcut = DB::table('teknik_servis_baski_sablonlari')
                ->where('firma_id', (int) $firmaId)
                ->where('sablon_turu', 'servis_formu')
                ->where('kod', 'yalova-bilgisayar-servis-kabul-formu-a4')
                ->first();

            $payload = [
                'ad' => (string) ($hazirSablon['ad'] ?? 'Yalova Bilgisayar Servis Kabul Formu'),
                'sayfa_tipi' => (string) ($hazirSablon['sayfa_tipi'] ?? 'a4'),
                'sablon_html' => (string) ($hazirSablon['sablon_html'] ?? ''),
                'sablon_css' => (string) ($hazirSablon['sablon_css'] ?? ''),
                'aktif' => true,
                'updated_at' => $simdi,
            ];

            if ($mevcut) {
                DB::table('teknik_servis_baski_sablonlari')
                    ->where('id', $mevcut->id)
                    ->update($payload);

                continue;
            }

            DB::table('teknik_servis_baski_sablonlari')->insert([
                'firma_id' => (int) $firmaId,
                'sablon_turu' => 'servis_formu',
                'ad' => (string) ($hazirSablon['ad'] ?? 'Yalova Bilgisayar Servis Kabul Formu'),
                'kod' => 'yalova-bilgisayar-servis-kabul-formu-a4',
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
        if (! Schema::hasTable('teknik_servis_baski_sablonlari')) {
            return;
        }

        DB::table('teknik_servis_baski_sablonlari')
            ->where('sablon_turu', 'servis_formu')
            ->where('kod', 'yalova-bilgisayar-servis-kabul-formu-a4')
            ->delete();
    }
};
