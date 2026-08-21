<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('muhasebe_satis_fis_sablonlari')) {
            return;
        }

        Schema::table('muhasebe_satis_fis_sablonlari', function (Blueprint $table): void {
            if (! Schema::hasColumn('muhasebe_satis_fis_sablonlari', 'sablon_logo')) {
                $table->string('sablon_logo', 255)->nullable()->after('sayfa_tipi');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('muhasebe_satis_fis_sablonlari')) {
            return;
        }

        Schema::table('muhasebe_satis_fis_sablonlari', function (Blueprint $table): void {
            if (Schema::hasColumn('muhasebe_satis_fis_sablonlari', 'sablon_logo')) {
                $table->dropColumn('sablon_logo');
            }
        });
    }
};

