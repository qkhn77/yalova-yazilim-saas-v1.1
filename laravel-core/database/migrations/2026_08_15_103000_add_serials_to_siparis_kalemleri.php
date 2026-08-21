<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('siparis_kalemleri', 'seri_nolari')) {
            Schema::table('siparis_kalemleri', function (Blueprint $table): void {
                $table->json('seri_nolari')->nullable()->after('stok_rezerv_miktari');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('siparis_kalemleri', 'seri_nolari')) {
            Schema::table('siparis_kalemleri', fn (Blueprint $table): Blueprint => $table->dropColumn('seri_nolari'));
        }
    }
};
