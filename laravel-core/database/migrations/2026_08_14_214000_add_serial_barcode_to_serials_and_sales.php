<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stok_seri_nolari') && ! Schema::hasColumn('stok_seri_nolari', 'barkod')) {
            Schema::table('stok_seri_nolari', function (Blueprint $table): void {
                $table->string('barkod', 191)->nullable()->after('seri_no');
                $table->unique(['firma_id', 'barkod']);
            });
        }

        if (Schema::hasTable('muhasebe_barkodlu_satis_kalemleri') && ! Schema::hasColumn('muhasebe_barkodlu_satis_kalemleri', 'seri_nolari')) {
            Schema::table('muhasebe_barkodlu_satis_kalemleri', function (Blueprint $table): void {
                $table->json('seri_nolari')->nullable()->after('barkod');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('muhasebe_barkodlu_satis_kalemleri', 'seri_nolari')) {
            Schema::table('muhasebe_barkodlu_satis_kalemleri', fn (Blueprint $table): Blueprint => $table->dropColumn('seri_nolari'));
        }
        if (Schema::hasColumn('stok_seri_nolari', 'barkod')) {
            Schema::table('stok_seri_nolari', function (Blueprint $table): void {
                $table->dropUnique(['firma_id', 'barkod']);
                $table->dropColumn('barkod');
            });
        }
    }
};
