<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teklifler') || Schema::hasColumn('teklifler', 'teklif_baski_sablonu_id')) {
            return;
        }

        Schema::table('teklifler', function (Blueprint $table): void {
            $table->foreignId('teklif_baski_sablonu_id')
                ->nullable()
                ->after('gecerlilik_tarihi')
                ->constrained('teklif_baski_sablonlari')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('teklifler') || ! Schema::hasColumn('teklifler', 'teklif_baski_sablonu_id')) {
            return;
        }

        Schema::table('teklifler', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('teklif_baski_sablonu_id');
        });
    }
};
