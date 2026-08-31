<?php

use App\Muhasebe\Servisler\CanonicalBirimGecisServisi;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('muhasebe_birimler')) {
            return;
        }

        // Production clone'daki legacy ADET sistem satirini, AD seed'i ikinci
        // bir semantik ikiz olusturmadan once canonical koda gecirir. Fresh
        // kurulumda tablo bos oldugu icin bu adim guvenli bir no-op'tur.
        if (DB::table('muhasebe_birimler')->whereIn('kod', ['AD', 'ADET'])->exists()) {
            CanonicalBirimGecisServisi::adetKodunuAdYap();
        }

        foreach ([
            ['AD', 'Adet'],
            ['KGM', 'Kilogram'],
        ] as [$kod, $ad]) {
            DB::table('muhasebe_birimler')->updateOrInsert(
                ['kod' => $kod, 'tanim_firma_kapsami' => 0],
                ['ad' => $ad, 'aktif_mi' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        // Keep system units; existing stock records may reference them.
    }
};
