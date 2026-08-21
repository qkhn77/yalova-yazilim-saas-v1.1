<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('teklifler')) {
            Schema::table('teklifler', function (Blueprint $table): void {
                $this->indexEkle($table, 'teklifler', ['firma_id', 'durum', 'tarih'], 'teklifler_firma_durum_tarih_idx');
                $this->indexEkle($table, 'teklifler', ['firma_id', 'cari_id', 'tarih'], 'teklifler_firma_cari_tarih_idx');
                $this->indexEkle($table, 'teklifler', ['firma_id', 'deleted_at', 'id'], 'teklifler_firma_deleted_id_idx');
            });
        }

        if (Schema::hasTable('teklif_kalemleri')) {
            Schema::table('teklif_kalemleri', function (Blueprint $table): void {
                $this->indexEkle($table, 'teklif_kalemleri', ['firma_id', 'teklif_id'], 'teklif_kalemleri_firma_teklif_idx');
            });
        }

        if (Schema::hasTable('teklif_baski_sablonlari')) {
            Schema::table('teklif_baski_sablonlari', function (Blueprint $table): void {
                $this->indexEkle($table, 'teklif_baski_sablonlari', ['firma_id', 'aktif', 'varsayilan_mi', 'id'], 'teklif_sablon_firma_aktif_varsayilan_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('teklif_baski_sablonlari')) {
            Schema::table('teklif_baski_sablonlari', function (Blueprint $table): void {
                $this->indexSil($table, 'teklif_baski_sablonlari', 'teklif_sablon_firma_aktif_varsayilan_idx');
            });
        }

        if (Schema::hasTable('teklif_kalemleri')) {
            Schema::table('teklif_kalemleri', function (Blueprint $table): void {
                $this->indexSil($table, 'teklif_kalemleri', 'teklif_kalemleri_firma_teklif_idx');
            });
        }

        if (Schema::hasTable('teklifler')) {
            Schema::table('teklifler', function (Blueprint $table): void {
                $this->indexSil($table, 'teklifler', 'teklifler_firma_deleted_id_idx');
                $this->indexSil($table, 'teklifler', 'teklifler_firma_cari_tarih_idx');
                $this->indexSil($table, 'teklifler', 'teklifler_firma_durum_tarih_idx');
            });
        }
    }

    /**
     * @param  array<int, string>  $kolonlar
     */
    private function indexEkle(Blueprint $table, string $tablo, array $kolonlar, string $indexAdi): void
    {
        if (! $this->indexVarMi($tablo, $indexAdi)) {
            $table->index($kolonlar, $indexAdi);
        }
    }

    private function indexSil(Blueprint $table, string $tablo, string $indexAdi): void
    {
        if ($this->indexVarMi($tablo, $indexAdi)) {
            $table->dropIndex($indexAdi);
        }
    }

    private function indexVarMi(string $tablo, string $indexAdi): bool
    {
        $schema = Schema::getFacadeRoot();
        if (method_exists($schema, 'hasIndex')) {
            return Schema::hasIndex($tablo, $indexAdi);
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            foreach (DB::select("PRAGMA index_list('{$tablo}')") as $index) {
                if (($index->name ?? null) === $indexAdi) {
                    return true;
                }
            }

            return false;
        }

        foreach (DB::select("SHOW INDEX FROM `{$tablo}` WHERE Key_name = ?", [$indexAdi]) as $index) {
            if (($index->Key_name ?? null) === $indexAdi) {
                return true;
            }
        }

        return false;
    }
};
