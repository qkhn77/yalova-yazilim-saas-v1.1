<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teknik_servis_kayitlari')) {
            return;
        }

        Schema::table('teknik_servis_kayitlari', function (Blueprint $table): void {
            if (! Schema::hasColumn('teknik_servis_kayitlari', 'cihaz_gorseller')) {
                $table->json('cihaz_gorseller')->nullable()->after('seri_no');
            }
        });

        if (Schema::hasColumn('teknik_servis_kayitlari', 'cari_gorseller')
            && Schema::hasColumn('teknik_servis_kayitlari', 'cihaz_gorseller')) {
            DB::table('teknik_servis_kayitlari')
                ->whereNull('cihaz_gorseller')
                ->whereNotNull('cari_gorseller')
                ->update(['cihaz_gorseller' => DB::raw('cari_gorseller')]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('teknik_servis_kayitlari')) {
            return;
        }

        Schema::table('teknik_servis_kayitlari', function (Blueprint $table): void {
            if (Schema::hasColumn('teknik_servis_kayitlari', 'cihaz_gorseller')) {
                $table->dropColumn('cihaz_gorseller');
            }
        });
    }
};
