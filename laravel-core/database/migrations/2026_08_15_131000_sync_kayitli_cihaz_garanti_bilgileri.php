<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('teknik_servis_kayitlari')
            ->select(['kayitli_cihaz_id', 'garanti_baslangic_tarihi', 'garanti_bitis_tarihi', 'bakim_tarihi', 'bakim_periyot_ay'])
            ->whereNotNull('kayitli_cihaz_id')
            ->where(function ($query): void {
                $query->whereNotNull('garanti_baslangic_tarihi')
                    ->orWhereNotNull('garanti_bitis_tarihi')
                    ->orWhereNotNull('bakim_tarihi');
            })
            ->orderByDesc('garanti_bitis_tarihi')
            ->orderByDesc('bakim_tarihi')
            ->orderByDesc('id')
            ->get()
            ->groupBy('kayitli_cihaz_id')
            ->each(function ($kayitlar, $cihazId): void {
                $kayit = $kayitlar->first();
                $bakim = $kayitlar->first(fn ($item): bool => filled($item->bakim_tarihi));

                DB::table('teknik_servis_kayitli_cihazlar')
                    ->where('id', $cihazId)
                    ->update([
                        'garanti_baslangic_tarihi' => $kayit->garanti_baslangic_tarihi,
                        'garanti_bitis_tarihi' => $kayit->garanti_bitis_tarihi,
                        'son_bakim_tarihi' => $bakim?->bakim_tarihi,
                        'bakim_periyot_ay' => $bakim?->bakim_periyot_ay,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // Bu aktarım mevcut servis verisini silmez; geri alma sırasında cihaz alanları korunur.
    }
};
