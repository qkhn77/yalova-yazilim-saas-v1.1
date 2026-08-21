<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('muhasebe_alacak_tahsilat_eslesmeleri', function (Blueprint $table): void {
            $table->unique(
                ['finans_hareketi_id', 'alacak_plan_taksiti_id'],
                'muh_alacak_eslesme_finans_taksit_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('muhasebe_alacak_tahsilat_eslesmeleri', function (Blueprint $table): void {
            $table->dropUnique('muh_alacak_eslesme_finans_taksit_unique');
        });
    }
};
