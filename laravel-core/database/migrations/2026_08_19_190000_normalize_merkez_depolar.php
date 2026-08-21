<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('muhasebe_depolar')) {
            return;
        }

        DB::table('muhasebe_depolar')
            ->where('kod', 'MERKEZ')
            ->whereNull('deleted_at')
            ->select('firma_id')
            ->distinct()
            ->pluck('firma_id')
            ->each(function (int $firmaId): void {
                DB::table('muhasebe_depolar')
                    ->where('firma_id', $firmaId)
                    ->update(['varsayilan_mi' => false]);

                DB::table('muhasebe_depolar')
                    ->where('firma_id', $firmaId)
                    ->where('kod', 'MERKEZ')
                    ->whereNull('deleted_at')
                    ->update([
                        'aktif_mi' => true,
                        'varsayilan_mi' => true,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // Existing default-depo choices are user data; do not unset them on rollback.
    }
};
