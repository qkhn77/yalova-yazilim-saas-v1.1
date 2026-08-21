<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stok_kategorileri', function (Blueprint $table): void {
            if (! Schema::hasColumn('stok_kategorileri', 'legacy_id')) {
                $table->string('legacy_id', 128)->nullable()->after('slug');
                $table->index(['firma_id', 'legacy_id'], 'stok_kategorileri_firma_legacy_idx');
            }
        });

        Schema::table('stok_kartlari', function (Blueprint $table): void {
            if (! Schema::hasColumn('stok_kartlari', 'legacy_id')) {
                $table->string('legacy_id', 128)->nullable()->after('slug');
                $table->index(['firma_id', 'legacy_id'], 'stok_kartlari_firma_legacy_idx');
            }
        });

        Schema::table('siparisler', function (Blueprint $table): void {
            if (! Schema::hasColumn('siparisler', 'legacy_id')) {
                $table->string('legacy_id', 128)->nullable()->after('siparis_no');
                $table->index(['firma_id', 'legacy_id'], 'siparisler_firma_legacy_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('siparisler', function (Blueprint $table): void {
            if (Schema::hasColumn('siparisler', 'legacy_id')) {
                $table->dropIndex('siparisler_firma_legacy_idx');
                $table->dropColumn('legacy_id');
            }
        });

        Schema::table('stok_kartlari', function (Blueprint $table): void {
            if (Schema::hasColumn('stok_kartlari', 'legacy_id')) {
                $table->dropIndex('stok_kartlari_firma_legacy_idx');
                $table->dropColumn('legacy_id');
            }
        });

        Schema::table('stok_kategorileri', function (Blueprint $table): void {
            if (Schema::hasColumn('stok_kategorileri', 'legacy_id')) {
                $table->dropIndex('stok_kategorileri_firma_legacy_idx');
                $table->dropColumn('legacy_id');
            }
        });
    }
};

