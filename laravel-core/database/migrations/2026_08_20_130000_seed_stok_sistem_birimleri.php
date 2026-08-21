<?php

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
