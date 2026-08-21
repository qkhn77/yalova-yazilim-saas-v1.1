<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_hesaplari', function (Blueprint $table): void {
            $table->index(['firma_id', 'created_at'], 'pos_hesaplari_firma_created_idx');
            $table->index(['firma_id', 'ad'], 'pos_hesaplari_firma_ad_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pos_hesaplari', function (Blueprint $table): void {
            $table->dropIndex('pos_hesaplari_firma_created_idx');
            $table->dropIndex('pos_hesaplari_firma_ad_idx');
        });
    }
};
