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
            if (! Schema::hasColumn('teknik_servis_kayitlari', 'bakim_tarihi')) {
                $table->date('bakim_tarihi')->nullable()->after('garanti_bitis_tarihi');
            }

            if (! Schema::hasColumn('teknik_servis_kayitlari', 'bakim_periyot_ay')) {
                $table->unsignedTinyInteger('bakim_periyot_ay')->nullable()->after('bakim_tarihi');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('teknik_servis_kayitlari')) {
            return;
        }

        Schema::table('teknik_servis_kayitlari', function (Blueprint $table): void {
            if (Schema::hasColumn('teknik_servis_kayitlari', 'bakim_periyot_ay')) {
                $table->dropColumn('bakim_periyot_ay');
            }

            if (Schema::hasColumn('teknik_servis_kayitlari', 'bakim_tarihi')) {
                $table->dropColumn('bakim_tarihi');
            }
        });
    }
};
