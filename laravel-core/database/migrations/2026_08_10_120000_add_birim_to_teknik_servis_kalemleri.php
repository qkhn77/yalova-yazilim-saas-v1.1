<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teknik_servis_kalemleri')
            || Schema::hasColumn('teknik_servis_kalemleri', 'birim')) {
            return;
        }

        Schema::table('teknik_servis_kalemleri', function (Blueprint $table): void {
            $table->string('birim', 32)->nullable()->after('stok_id');
        });

        DB::table('teknik_servis_kalemleri')
            ->select(['id', 'stok_id'])
            ->orderBy('id')
            ->chunkById(500, function ($kalemler): void {
                $stokIdleri = $kalemler
                    ->pluck('stok_id')
                    ->filter()
                    ->map(fn ($id): int => (int) $id)
                    ->unique()
                    ->values();

                $birimler = $stokIdleri->isEmpty()
                    ? collect()
                    : DB::table('stok_kartlari')
                        ->whereIn('id', $stokIdleri)
                        ->pluck('birim', 'id');

                foreach ($kalemler as $kalem) {
                    DB::table('teknik_servis_kalemleri')
                        ->where('id', $kalem->id)
                        ->update([
                            'birim' => strtoupper(trim((string) ($birimler[(int) $kalem->stok_id] ?? 'AD'))) ?: 'AD',
                        ]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('teknik_servis_kalemleri')
            && Schema::hasColumn('teknik_servis_kalemleri', 'birim')) {
            Schema::table('teknik_servis_kalemleri', function (Blueprint $table): void {
                $table->dropColumn('birim');
            });
        }
    }
};
