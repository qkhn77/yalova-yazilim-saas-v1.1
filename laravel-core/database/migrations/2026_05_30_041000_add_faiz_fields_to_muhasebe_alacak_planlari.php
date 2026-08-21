<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('muhasebe_alacak_planlari', function (Blueprint $table): void {
            if (! Schema::hasColumn('muhasebe_alacak_planlari', 'faiz_orani')) {
                $table->decimal('faiz_orani', 8, 4)->default(0)->after('pesinat_tutari');
            }

            if (! Schema::hasColumn('muhasebe_alacak_planlari', 'faiz_tutari')) {
                $table->decimal('faiz_tutari', 18, 2)->default(0)->after('faiz_orani');
            }
        });
    }

    public function down(): void
    {
        Schema::table('muhasebe_alacak_planlari', function (Blueprint $table): void {
            if (Schema::hasColumn('muhasebe_alacak_planlari', 'faiz_tutari')) {
                $table->dropColumn('faiz_tutari');
            }

            if (Schema::hasColumn('muhasebe_alacak_planlari', 'faiz_orani')) {
                $table->dropColumn('faiz_orani');
            }
        });
    }
};
