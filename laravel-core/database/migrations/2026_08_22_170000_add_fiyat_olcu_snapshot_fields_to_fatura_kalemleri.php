<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fatura_kalemleri')) {
            return;
        }

        Schema::table('fatura_kalemleri', function (Blueprint $table): void {
            if (! Schema::hasColumn('fatura_kalemleri', 'fiyat_birimi_id')) {
                $table->foreignId('fiyat_birimi_id')
                    ->nullable()
                    ->after('olcu_donusum_snapshot')
                    ->constrained('muhasebe_birimler')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('fatura_kalemleri', 'fiyat_miktari')) {
                $table->decimal('fiyat_miktari', 20, 8)
                    ->nullable()
                    ->after('fiyat_birimi_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fatura_kalemleri')) {
            return;
        }

        Schema::table('fatura_kalemleri', function (Blueprint $table): void {
            if (Schema::hasColumn('fatura_kalemleri', 'fiyat_birimi_id')) {
                $table->dropConstrainedForeignId('fiyat_birimi_id');
            }

            if (Schema::hasColumn('fatura_kalemleri', 'fiyat_miktari')) {
                $table->dropColumn('fiyat_miktari');
            }
        });
    }
};
