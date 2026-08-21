<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fatura_kalemleri', 'depo_id')) {
            Schema::table('fatura_kalemleri', function (Blueprint $table): void {
                $table->foreignId('depo_id')->nullable()->after('stok_id')->constrained('muhasebe_depolar')->nullOnDelete();
                $table->index(['firma_id', 'depo_id', 'stok_id'], 'fatura_kalemleri_firma_depo_stok_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fatura_kalemleri', 'depo_id')) {
            Schema::table('fatura_kalemleri', function (Blueprint $table): void {
                $table->dropForeign(['depo_id']);
                $table->dropIndex('fatura_kalemleri_firma_depo_stok_idx');
                $table->dropColumn('depo_id');
            });
        }
    }
};
