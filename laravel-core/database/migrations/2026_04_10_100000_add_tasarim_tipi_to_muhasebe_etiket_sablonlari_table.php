<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('muhasebe_etiket_sablonlari')) {
            return;
        }

        Schema::table('muhasebe_etiket_sablonlari', function (Blueprint $table): void {
            if (! Schema::hasColumn('muhasebe_etiket_sablonlari', 'tasarim_tipi')) {
                $table->string('tasarim_tipi', 32)->default('standart')->after('barkod_tipi');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('muhasebe_etiket_sablonlari')) {
            return;
        }

        Schema::table('muhasebe_etiket_sablonlari', function (Blueprint $table): void {
            if (Schema::hasColumn('muhasebe_etiket_sablonlari', 'tasarim_tipi')) {
                $table->dropColumn('tasarim_tipi');
            }
        });
    }
};

