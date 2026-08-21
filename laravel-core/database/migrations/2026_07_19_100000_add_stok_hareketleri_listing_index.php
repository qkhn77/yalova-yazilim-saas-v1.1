<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'stok_hareketleri_firma_durum_islem_tarihi_id_idx';

    public function up(): void
    {
        if (! Schema::hasTable('stok_hareketleri')) {
            return;
        }

        $indexExists = collect(Schema::getIndexes('stok_hareketleri'))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === self::INDEX_NAME);

        if ($indexExists) {
            return;
        }

        Schema::table('stok_hareketleri', function (Blueprint $table): void {
            $table->index(
                ['firma_id', 'durum', 'islem_tarihi', 'id'],
                self::INDEX_NAME,
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('stok_hareketleri')) {
            return;
        }

        $indexExists = collect(Schema::getIndexes('stok_hareketleri'))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === self::INDEX_NAME);

        if (! $indexExists) {
            return;
        }

        Schema::table('stok_hareketleri', function (Blueprint $table): void {
            $table->dropIndex(self::INDEX_NAME);
        });
    }
};
