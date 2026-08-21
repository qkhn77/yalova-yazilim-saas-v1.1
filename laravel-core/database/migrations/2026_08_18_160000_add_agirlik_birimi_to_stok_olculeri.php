<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stok_olculeri', function (Blueprint $table): void {
            if (! Schema::hasColumn('stok_olculeri', 'agirlik_birimi')) {
                $table->string('agirlik_birimi', 8)->nullable()->after('bir_adet_agirlik');
            }
        });

        DB::table('stok_olculeri')
            ->where('takip_turu', 'agirlik')
            ->whereNull('agirlik_birimi')
            ->update(['agirlik_birimi' => 'kg']);
    }

    public function down(): void
    {
        Schema::table('stok_olculeri', function (Blueprint $table): void {
            if (Schema::hasColumn('stok_olculeri', 'agirlik_birimi')) {
                $table->dropColumn('agirlik_birimi');
            }
        });
    }
};
