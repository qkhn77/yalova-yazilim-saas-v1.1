<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teknik_servis_kayitlari')) {
            return;
        }

        Schema::table('teknik_servis_kayitlari', function (Blueprint $table): void {
            if (Schema::hasColumn('teknik_servis_kayitlari', 'cari_gorseller')) {
                $table->dropColumn('cari_gorseller');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('teknik_servis_kayitlari')) {
            return;
        }

        Schema::table('teknik_servis_kayitlari', function (Blueprint $table): void {
            if (! Schema::hasColumn('teknik_servis_kayitlari', 'cari_gorseller')) {
                $table->json('cari_gorseller')->nullable()->after('musteri_tel');
            }
        });
    }
};
