<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('muhasebe_doviz_kurlari')) {
            return;
        }

        Schema::table('muhasebe_doviz_kurlari', function (Blueprint $table): void {
            if (! Schema::hasColumn('muhasebe_doviz_kurlari', 'saglayici')) {
                $table->string('saglayici', 32)->nullable()->after('kur');
            }
        });

        DB::table('muhasebe_doviz_kurlari')
            ->whereNull('saglayici')
            ->update([
                'saglayici' => DB::raw("CASE WHEN manuel_mi = 1 THEN 'manuel' ELSE 'tcmb' END"),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('muhasebe_doviz_kurlari')) {
            return;
        }

        Schema::table('muhasebe_doviz_kurlari', function (Blueprint $table): void {
            if (Schema::hasColumn('muhasebe_doviz_kurlari', 'saglayici')) {
                $table->dropColumn('saglayici');
            }
        });
    }
};
