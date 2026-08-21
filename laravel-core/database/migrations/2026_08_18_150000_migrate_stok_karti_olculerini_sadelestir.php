<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('stok_kartlari')
            ->whereIn('olculu_takip_turu', ['uzunluk', 'alan', 'hacim', 'agirlik'])
            ->orderBy('id')
            ->get(['id', 'firma_id', 'ad', 'olculu_takip_turu', 'en_cm', 'boy_cm', 'kalinlik_cm', 'urun_agirligi'])
            ->each(function (object $stok): void {
                $olcu = DB::table('stok_olculeri')->where('stok_id', $stok->id)->whereNull('deleted_at')->orderBy('id')->first();
                if ($olcu) {
                    return;
                }
                $en = $stok->en_cm !== null ? (float) $stok->en_cm : null;
                $boy = $stok->boy_cm !== null ? (float) $stok->boy_cm : null;
                $kalinlik = $stok->kalinlik_cm !== null ? (float) $stok->kalinlik_cm : null;
                $agirlik = $stok->urun_agirligi !== null ? (float) $stok->urun_agirligi : null;
                $anaMiktar = match ($stok->olculu_takip_turu) {
                    'uzunluk' => $boy !== null ? $boy / 100 : null,
                    'alan' => $en !== null && $boy !== null ? ($en * $boy) / 10000 : null,
                    'hacim' => $en !== null && $boy !== null && $kalinlik !== null ? ($en * $boy * $kalinlik) / 1000000 : null,
                    'agirlik' => $agirlik,
                    default => null,
                };
                if ($en === null && $boy === null && $kalinlik === null && $agirlik === null) {
                    return;
                }
                DB::table('stok_olculeri')->insert([
                    'firma_id' => $stok->firma_id, 'stok_id' => $stok->id,
                    'kod' => 'MIGRATED-'.$stok->id, 'ad' => $stok->ad.' ölçüsü',
                    'takip_turu' => $stok->olculu_takip_turu, 'olcu_birimi' => 'cm',
                    'en' => $en, 'boy' => $boy, 'yukseklik' => $kalinlik, 'bir_adet_agirlik' => $agirlik,
                    'en_m' => $en !== null ? $en / 100 : null, 'boy_m' => $boy !== null ? $boy / 100 : null,
                    'yukseklik_m' => $kalinlik !== null ? $kalinlik / 100 : null,
                    'bir_adet_ana_miktar' => $anaMiktar, 'aktif_mi' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            });

        Schema::table('stok_kartlari', function (Blueprint $table): void {
            $table->dropColumn(['en_cm', 'boy_cm', 'kalinlik_cm', 'urun_agirligi']);
        });
    }

    public function down(): void
    {
        Schema::table('stok_kartlari', function (Blueprint $table): void {
            $table->decimal('en_cm', 10, 2)->nullable();
            $table->decimal('boy_cm', 10, 2)->nullable();
            $table->decimal('kalinlik_cm', 10, 2)->nullable();
            $table->decimal('urun_agirligi', 10, 2)->nullable();
        });
    }
};
