<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cariler') || Schema::hasColumn('cariler', 'cari_grubu_id')) {
            return;
        }

        Schema::table('cariler', function (Blueprint $table): void {
            $table->foreignId('cari_grubu_id')
                ->nullable()
                ->after('firma_id')
                ->constrained('muhasebe_cari_gruplari')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cariler') || ! Schema::hasColumn('cariler', 'cari_grubu_id')) {
            return;
        }

        Schema::table('cariler', function (Blueprint $table): void {
            $table->dropForeign(['cari_grubu_id']);
            $table->dropColumn('cari_grubu_id');
        });
    }
};
