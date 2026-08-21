<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restoran_adisyonlari', function (Blueprint $table): void {
            if (! Schema::hasColumn('restoran_adisyonlari', 'kurye_personel_id')) {
                $table->foreignId('kurye_personel_id')->nullable()->after('kasiyer_personel_id')->constrained('personeller')->nullOnDelete();
            }

            if (! Schema::hasColumn('restoran_adisyonlari', 'paket_durum')) {
                $table->string('paket_durum', 32)->nullable()->after('siparis_tipi');
            }

            if (! Schema::hasColumn('restoran_adisyonlari', 'teslimat_telefon')) {
                $table->string('teslimat_telefon', 32)->nullable()->after('paket_durum');
            }

            if (! Schema::hasColumn('restoran_adisyonlari', 'teslimat_adresi')) {
                $table->text('teslimat_adresi')->nullable()->after('teslimat_telefon');
            }

            if (! Schema::hasColumn('restoran_adisyonlari', 'teslimat_at')) {
                $table->dateTime('teslimat_at')->nullable()->after('tahsilat_at');
            }
        });

        $this->indexOlustur('restoran_adisyonlari', ['paket_durum'], 'restoran_adisyonlari_paket_durum_index');
        $this->indexOlustur('restoran_adisyonlari', ['firma_id', 'kurye_personel_id', 'teslimat_at'], 'restoran_adisyon_kurye_teslimat_idx');
        $this->indexOlustur('restoran_adisyonlari', ['firma_id', 'siparis_tipi', 'paket_durum'], 'restoran_adisyon_paket_durum_idx');
    }

    public function down(): void
    {
        Schema::table('restoran_adisyonlari', function (Blueprint $table): void {
            if ($this->indexVarMi('restoran_adisyonlari', 'restoran_adisyon_kurye_teslimat_idx')) {
                $table->dropIndex('restoran_adisyon_kurye_teslimat_idx');
            }

            if ($this->indexVarMi('restoran_adisyonlari', 'restoran_adisyon_paket_durum_idx')) {
                $table->dropIndex('restoran_adisyon_paket_durum_idx');
            }

            if ($this->indexVarMi('restoran_adisyonlari', 'restoran_adisyonlari_paket_durum_index')) {
                $table->dropIndex('restoran_adisyonlari_paket_durum_index');
            }

            if (Schema::hasColumn('restoran_adisyonlari', 'kurye_personel_id')) {
                $table->dropConstrainedForeignId('kurye_personel_id');
            }

            foreach (['paket_durum', 'teslimat_telefon', 'teslimat_adresi', 'teslimat_at'] as $kolon) {
                if (Schema::hasColumn('restoran_adisyonlari', $kolon)) {
                    $table->dropColumn($kolon);
                }
            }
        });
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
