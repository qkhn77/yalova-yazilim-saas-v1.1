<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stok_hareketleri')) {
            return;
        }

        if (! Schema::hasColumn('stok_hareketleri', 'cari_id')) {
            Schema::table('stok_hareketleri', function (Blueprint $table): void {
                $table->foreignId('cari_id')
                    ->nullable()
                    ->after('firma_id')
                    ->constrained('cariler')
                    ->nullOnDelete();
            });
        }

        $indexExists = collect(Schema::getIndexes('stok_hareketleri'))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === 'stok_hareketleri_firma_cari_islem_tarihi_id_idx');

        if (! $indexExists) {
            Schema::table('stok_hareketleri', function (Blueprint $table): void {
                $table->index(
                    ['firma_id', 'cari_id', 'islem_tarihi', 'id'],
                    'stok_hareketleri_firma_cari_islem_tarihi_id_idx'
                );
            });
        }

        $this->backfillCari('fatura', 'faturalar');
        $this->backfillCari('restoran_adisyon', 'restoran_adisyonlari');
    }

    public function down(): void
    {
        if (! Schema::hasTable('stok_hareketleri')) {
            return;
        }

        $indexExists = collect(Schema::getIndexes('stok_hareketleri'))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === 'stok_hareketleri_firma_cari_islem_tarihi_id_idx');

        if ($indexExists) {
            Schema::table('stok_hareketleri', function (Blueprint $table): void {
                $table->dropIndex('stok_hareketleri_firma_cari_islem_tarihi_id_idx');
            });
        }

        if (Schema::hasColumn('stok_hareketleri', 'cari_id')) {
            Schema::table('stok_hareketleri', function (Blueprint $table): void {
                $table->dropForeign(['cari_id']);
                $table->dropColumn('cari_id');
            });
        }
    }

    private function backfillCari(string $referansTipi, string $kaynakTablosu): void
    {
        if (! Schema::hasTable($kaynakTablosu)) {
            return;
        }

        DB::table('stok_hareketleri')
            ->where('referans_tipi', $referansTipi)
            ->whereNull('cari_id')
            ->orderBy('id')
            ->chunkById(500, function ($hareketler) use ($kaynakTablosu): void {
                foreach ($hareketler as $hareket) {
                    $cariId = DB::table($kaynakTablosu)
                        ->where('id', $hareket->referans_id)
                        ->where('firma_id', $hareket->firma_id)
                        ->value('cari_id');

                    if ($cariId !== null) {
                        DB::table('stok_hareketleri')
                            ->where('id', $hareket->id)
                            ->update(['cari_id' => $cariId]);
                    }
                }
            });
    }
};
