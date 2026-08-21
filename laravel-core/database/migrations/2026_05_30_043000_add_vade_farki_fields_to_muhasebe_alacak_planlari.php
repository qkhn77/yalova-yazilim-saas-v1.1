<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('muhasebe_alacak_planlari', function (Blueprint $table): void {
            if (! Schema::hasColumn('muhasebe_alacak_planlari', 'vade_farki_tipi')) {
                $table->string('vade_farki_tipi', 24)->default('tek_seferlik')->after('pesinat_tutari');
            }

            if (! Schema::hasColumn('muhasebe_alacak_planlari', 'vade_farki_orani')) {
                $table->decimal('vade_farki_orani', 8, 4)->default(0)->after('vade_farki_tipi');
            }

            if (! Schema::hasColumn('muhasebe_alacak_planlari', 'vade_farki_tutari')) {
                $table->decimal('vade_farki_tutari', 18, 2)->default(0)->after('vade_farki_orani');
            }
        });
    }

    public function down(): void
    {
        Schema::table('muhasebe_alacak_planlari', function (Blueprint $table): void {
            foreach (['vade_farki_tutari', 'vade_farki_orani', 'vade_farki_tipi'] as $kolon) {
                if (Schema::hasColumn('muhasebe_alacak_planlari', $kolon)) {
                    $table->dropColumn($kolon);
                }
            }
        });
    }
};
