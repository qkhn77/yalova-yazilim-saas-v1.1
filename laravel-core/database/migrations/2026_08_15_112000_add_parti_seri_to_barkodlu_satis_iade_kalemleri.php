<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('muhasebe_barkodlu_satis_iade_kalemleri', function (Blueprint $table): void {
            if (! Schema::hasColumn('muhasebe_barkodlu_satis_iade_kalemleri', 'parca_kodu')) {
                $table->string('parca_kodu', 120)->nullable()->after('stok_id');
            }
            if (! Schema::hasColumn('muhasebe_barkodlu_satis_iade_kalemleri', 'parca_dagilimi')) {
                $table->json('parca_dagilimi')->nullable()->after('parca_kodu');
            }
            if (! Schema::hasColumn('muhasebe_barkodlu_satis_iade_kalemleri', 'seri_nolari')) {
                $table->json('seri_nolari')->nullable()->after('parca_dagilimi');
            }
        });
    }

    public function down(): void
    {
        Schema::table('muhasebe_barkodlu_satis_iade_kalemleri', function (Blueprint $table): void {
            $columns = [];
            foreach (['parca_kodu', 'parca_dagilimi', 'seri_nolari'] as $column) {
                if (Schema::hasColumn('muhasebe_barkodlu_satis_iade_kalemleri', $column)) {
                    $columns[] = $column;
                }
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
