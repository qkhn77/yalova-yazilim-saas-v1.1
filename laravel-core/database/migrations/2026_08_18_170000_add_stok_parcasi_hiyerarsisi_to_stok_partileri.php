<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stok_parcalari')) {
            return;
        }

        Schema::table('stok_parcalari', function (Blueprint $table): void {
            if (! Schema::hasColumn('stok_parcalari', 'ust_parca_id')) {
                $table->foreignId('ust_parca_id')->nullable()->after('depo_id')->constrained('stok_parcalari')->nullOnDelete();
            }
            if (! Schema::hasColumn('stok_parcalari', 'parca_kodu')) {
                $table->string('parca_kodu', 128)->nullable()->after('parca_kodu');
            }
            if (! Schema::hasColumn('stok_parcalari', 'barkod')) {
                $table->string('barkod', 128)->nullable()->after('parca_kodu');
            }
            if (! Schema::hasColumn('stok_parcalari', 'parca_mi')) {
                $table->boolean('parca_mi')->default(false)->after('barkod');
            }
            if (! Schema::hasColumn('stok_parcalari', 'parca_durumu')) {
                $table->string('parca_durumu', 16)->default('aktif')->after('parca_mi');
            }
        });

        Schema::table('stok_parcalari', function (Blueprint $table): void {
            $table->index(['firma_id', 'stok_id', 'parca_mi', 'parca_durumu'], 'stok_parcalari_parca_durum_idx');
            $table->index(['firma_id', 'ust_parca_id'], 'stok_parcalari_ust_parca_idx');
            $table->unique(['firma_id', 'parca_kodu'], 'stok_parcalari_firma_parca_kodu_uniq');
            $table->unique(['firma_id', 'barkod'], 'stok_parcalari_firma_barkod_uniq');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('stok_parcalari')) {
            return;
        }

        Schema::table('stok_parcalari', function (Blueprint $table): void {
            $table->dropUnique('stok_parcalari_firma_parca_kodu_uniq');
            $table->dropUnique('stok_parcalari_firma_barkod_uniq');
            $table->dropIndex('stok_parcalari_parca_durum_idx');
            $table->dropIndex('stok_parcalari_ust_parca_idx');
            if (Schema::hasColumn('stok_parcalari', 'ust_parca_id')) {
                $table->dropForeign(['ust_parca_id']);
            }
            $columns = array_values(array_filter(['ust_parca_id', 'parca_kodu', 'barkod', 'parca_mi', 'parca_durumu'], fn (string $column): bool => Schema::hasColumn('stok_parcalari', $column)));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
