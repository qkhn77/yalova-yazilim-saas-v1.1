<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finans_hareketleri', function (Blueprint $table): void {
            $table->unsignedBigInteger('duzeltme_kaynagi_id')->nullable()->after('iptal_edilen_hareket_id');
            $table->index('duzeltme_kaynagi_id', 'finans_hareketleri_duzeltme_kaynagi_idx');
        });
    }

    public function down(): void
    {
        Schema::table('finans_hareketleri', function (Blueprint $table): void {
            $table->dropIndex('finans_hareketleri_duzeltme_kaynagi_idx');
            $table->dropColumn('duzeltme_kaynagi_id');
        });
    }
};
