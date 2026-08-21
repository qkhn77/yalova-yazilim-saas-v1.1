<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finans_hareketleri', function (Blueprint $table): void {
            $table->unique('iptal_edilen_hareket_id', 'finans_hareketleri_iptal_edilen_unique');
        });
    }

    public function down(): void
    {
        Schema::table('finans_hareketleri', function (Blueprint $table): void {
            $table->dropUnique('finans_hareketleri_iptal_edilen_unique');
        });
    }
};
