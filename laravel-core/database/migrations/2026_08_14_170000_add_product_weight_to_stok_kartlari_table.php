<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('stok_kartlari', 'urun_agirligi')) {
            Schema::table('stok_kartlari', function (Blueprint $table): void {
                $table->decimal('urun_agirligi', 10, 2)->nullable()->after('kalinlik_cm');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('stok_kartlari', 'urun_agirligi')) {
            Schema::table('stok_kartlari', function (Blueprint $table): void {
                $table->dropColumn('urun_agirligi');
            });
        }
    }
};
