<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['muhasebe_alacak_plan_taksitleri', 'muhasebe_alacak_tahsilat_eslesmeleri'] as $tablo) {
            if (! Schema::hasTable($tablo)) {
                continue;
            }

            Schema::table($tablo, function (Blueprint $table): void {
                if (! Schema::hasColumn($table->getTable(), 'para_birimi')) {
                    $table->char('para_birimi', 3)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['muhasebe_alacak_plan_taksitleri', 'muhasebe_alacak_tahsilat_eslesmeleri'] as $tablo) {
            if (! Schema::hasTable($tablo)) {
                continue;
            }

            Schema::table($tablo, function (Blueprint $table): void {
                if (Schema::hasColumn($table->getTable(), 'para_birimi')) {
                    $table->dropColumn('para_birimi');
                }
            });
        }
    }
};
