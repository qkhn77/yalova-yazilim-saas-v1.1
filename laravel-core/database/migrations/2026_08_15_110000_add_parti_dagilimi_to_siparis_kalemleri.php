<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('siparis_kalemleri', 'parca_kodu')) {
            Schema::table('siparis_kalemleri', function (Blueprint $table): void {
                $table->string('parca_kodu', 128)->nullable()->after('depo_id');
                $table->json('parca_dagilimi')->nullable()->after('parca_kodu');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('siparis_kalemleri', 'parca_kodu')) {
            Schema::table('siparis_kalemleri', function (Blueprint $table): void {
                $table->dropColumn(['parca_kodu', 'parca_dagilimi']);
            });
        }
    }
};
