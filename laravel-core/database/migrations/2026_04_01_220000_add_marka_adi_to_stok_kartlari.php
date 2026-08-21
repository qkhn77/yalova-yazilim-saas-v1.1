<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stok_kartlari', function (Blueprint $table): void {
            if (! Schema::hasColumn('stok_kartlari', 'marka_adi')) {
                $table->string('marka_adi', 255)->nullable()->after('marka_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stok_kartlari', function (Blueprint $table): void {
            if (Schema::hasColumn('stok_kartlari', 'marka_adi')) {
                $table->dropColumn('marka_adi');
            }
        });
    }
};
