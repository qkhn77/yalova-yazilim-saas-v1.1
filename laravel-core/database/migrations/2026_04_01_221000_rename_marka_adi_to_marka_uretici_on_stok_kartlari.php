<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stok_kartlari', function (Blueprint $table): void {
            if (Schema::hasColumn('stok_kartlari', 'marka_adi') && ! Schema::hasColumn('stok_kartlari', 'marka_uretici')) {
                $table->renameColumn('marka_adi', 'marka_uretici');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stok_kartlari', function (Blueprint $table): void {
            if (Schema::hasColumn('stok_kartlari', 'marka_uretici') && ! Schema::hasColumn('stok_kartlari', 'marka_adi')) {
                $table->renameColumn('marka_uretici', 'marka_adi');
            }
        });
    }
};
