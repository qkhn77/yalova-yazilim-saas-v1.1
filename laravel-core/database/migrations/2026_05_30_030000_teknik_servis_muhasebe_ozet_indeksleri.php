<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->indexEkle(
            'teknik_servis_tahsilatlari',
            ['firma_id', 'teknik_servis_kaydi_id', 'deleted_at', 'durum', 'tutar'],
            'ts_tahsilat_kayit_sil_durum_tutar_idx'
        );

        $this->indexEkle(
            'teknik_servis_muhasebe_baglantilari',
            ['firma_id', 'teknik_servis_kaydi_id', 'islem_tipi', 'id'],
            'ts_muhasebe_kayit_islem_id_idx'
        );
    }

    public function down(): void
    {
        foreach ([
            'teknik_servis_muhasebe_baglantilari' => 'ts_muhasebe_kayit_islem_id_idx',
            'teknik_servis_tahsilatlari' => 'ts_tahsilat_kayit_sil_durum_tutar_idx',
        ] as $tablo => $indexAdi) {
            if (! Schema::hasTable($tablo) || ! $this->indexVarMi($tablo, $indexAdi)) {
                continue;
            }

            Schema::table($tablo, function (Blueprint $table) use ($indexAdi): void {
                $table->dropIndex($indexAdi);
            });
        }
    }

    /**
     * @param  array<int, string>  $kolonlar
     */
    private function indexEkle(string $tablo, array $kolonlar, string $indexAdi): void
    {
        if (! Schema::hasTable($tablo)) {
            return;
        }

        foreach ($kolonlar as $kolon) {
            if (! Schema::hasColumn($tablo, $kolon)) {
                return;
            }
        }

        if ($this->indexVarMi($tablo, $indexAdi)) {
            return;
        }

        Schema::table($tablo, function (Blueprint $table) use ($kolonlar, $indexAdi): void {
            $table->index($kolonlar, $indexAdi);
        });
    }

    private function indexVarMi(string $tablo, string $indexAdi): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $satir = DB::selectOne(
                'SELECT COUNT(1) AS c FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
                [$tablo, $indexAdi]
            );

            return isset($satir->c) && (int) $satir->c > 0;
        }

        if ($driver === 'sqlite') {
            $satir = DB::selectOne(
                'SELECT COUNT(1) AS c FROM sqlite_master WHERE type = ? AND tbl_name = ? AND name = ?',
                ['index', $tablo, $indexAdi]
            );

            return isset($satir->c) && (int) $satir->c > 0;
        }

        return false;
    }
};
