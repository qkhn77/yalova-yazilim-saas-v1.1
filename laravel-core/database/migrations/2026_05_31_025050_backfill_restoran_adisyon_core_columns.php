<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('restoran_adisyonlari')) {
            return;
        }

        Schema::table('restoran_adisyonlari', function (Blueprint $table): void {
            if (! Schema::hasColumn('restoran_adisyonlari', 'cari_id')) {
                $table->foreignId('cari_id')->nullable()->after('masa_id')->constrained('cariler')->nullOnDelete();
            }

            if (! Schema::hasColumn('restoran_adisyonlari', 'kasa_hesap_id')) {
                $table->foreignId('kasa_hesap_id')->nullable()->after('kasiyer_personel_id')->constrained('kasa_hesaplari')->nullOnDelete();
            }

            if (! Schema::hasColumn('restoran_adisyonlari', 'banka_hesap_id')) {
                $table->foreignId('banka_hesap_id')->nullable()->after('kasa_hesap_id')->constrained('banka_hesaplari')->nullOnDelete();
            }

            if (! Schema::hasColumn('restoran_adisyonlari', 'pos_hesap_id')) {
                $table->foreignId('pos_hesap_id')->nullable()->after('banka_hesap_id')->constrained('pos_hesaplari')->nullOnDelete();
            }

            if (! Schema::hasColumn('restoran_adisyonlari', 'finans_hareketi_id')) {
                $table->foreignId('finans_hareketi_id')->nullable()->after('pos_hesap_id')->constrained('finans_hareketleri')->nullOnDelete();
            }

            if (! Schema::hasColumn('restoran_adisyonlari', 'siparis_tipi')) {
                $table->string('siparis_tipi', 32)->default('masa')->after('durum');
            }

            if (! Schema::hasColumn('restoran_adisyonlari', 'odeme_kanali')) {
                $table->string('odeme_kanali', 32)->nullable()->after('siparis_tipi');
            }

            if (! Schema::hasColumn('restoran_adisyonlari', 'tahsilat_at')) {
                $table->dateTime('tahsilat_at')->nullable()->after('para_birimi');
            }
        });

        $this->indexOlustur('restoran_adisyonlari', ['firma_id', 'finans_hareketi_id'], 'rest_adisyon_finans_idx');
        $this->indexOlustur('restoran_adisyonlari', ['firma_id', 'kasiyer_personel_id', 'tahsilat_at'], 'rest_adisyon_kasiyer_tahsilat_idx');
        $this->indexOlustur('restoran_adisyonlari', ['firma_id', 'durum', 'acilis_at'], 'rest_adisyon_durum_acilis_idx');
        $this->indexOlustur('restoran_adisyonlari', ['siparis_tipi'], 'rest_adisyon_siparis_tipi_idx');
    }

    public function down(): void
    {
        // Geriye donuk tamamlayici migration veri kaybi riski nedeniyle kolon dusurmez.
    }

    /**
     * @param  array<int, string>  $kolonlar
     */
    private function indexOlustur(string $tablo, array $kolonlar, string $indexAdi): void
    {
        if ($this->indexVarMi($tablo, $indexAdi)) {
            return;
        }

        Schema::table($tablo, function (Blueprint $table) use ($kolonlar, $indexAdi): void {
            $table->index($kolonlar, $indexAdi);
        });
    }

    private function indexVarMi(string $tablo, string $indexAdi): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexler = DB::select("PRAGMA index_list('".$tablo."')");

            foreach ($indexler as $index) {
                if ((string) ($index->name ?? '') === $indexAdi) {
                    return true;
                }
            }

            return false;
        }

        $sonuclar = DB::select('SHOW INDEX FROM `'.$tablo.'` WHERE Key_name = ?', [$indexAdi]);

        return count($sonuclar) > 0;
    }
};
