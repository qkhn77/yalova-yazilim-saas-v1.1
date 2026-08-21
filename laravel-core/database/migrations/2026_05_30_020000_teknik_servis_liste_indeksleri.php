<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teknik_servis_kayitlari')) {
            return;
        }

        $this->indexEkle(
            'teknik_servis_kayitlari',
            ['firma_id', 'deleted_at', 'kabul_tarihi'],
            'ts_kayit_firma_sil_kabul_idx'
        );

        $this->indexEkle(
            'teknik_servis_kayitlari',
            ['firma_id', 'servis_durumu_id', 'deleted_at', 'kabul_tarihi'],
            'ts_kayit_firma_durum_sil_kabul_idx'
        );

        $this->indexEkle(
            'teknik_servis_kayitlari',
            ['firma_id', 'servis_tipi', 'deleted_at', 'kabul_tarihi'],
            'ts_kayit_firma_tip_sil_kabul_idx'
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('teknik_servis_kayitlari')) {
            return;
        }

        foreach ([
            'ts_kayit_firma_tip_sil_kabul_idx',
            'ts_kayit_firma_durum_sil_kabul_idx',
            'ts_kayit_firma_sil_kabul_idx',
        ] as $indexAdi) {
            if (! $this->indexVarMi('teknik_servis_kayitlari', $indexAdi)) {
                continue;
            }

            Schema::table('teknik_servis_kayitlari', function (Blueprint $table) use ($indexAdi): void {
                $table->dropIndex($indexAdi);
            });
        }
    }

    /**
     * @param  array<int, string>  $kolonlar
     */
    private function indexEkle(string $tablo, array $kolonlar, string $indexAdi): void
    {
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
