<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tablolar = [
        'cariler',
        'kasa_hesaplari',
        'banka_hesaplari',
        'pos_hesaplari',
        'stok_kartlari',
        'faturalar',
        'cari_hareketleri',
        'stok_hareketleri',
        'finans_hareketleri',
        'kasa_hareketleri',
        'banka_hareketleri',
        'pos_hareketleri',
        'fatura_numara_sayaclari',
    ];

    public function up(): void
    {
        foreach ($this->tablolar as $tablo) {
            if (! Schema::hasTable($tablo)) {
                continue;
            }
            Schema::table($tablo, function (Blueprint $table) use ($tablo) {
                if (! Schema::hasColumn($tablo, 'firma_id')) {
                    return;
                }
                $table->dropForeign(['firma_id']);
                $table->foreign('firma_id')->references('id')->on('firmalar')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tablolar as $tablo) {
            if (! Schema::hasTable($tablo)) {
                continue;
            }
            Schema::table($tablo, function (Blueprint $table) use ($tablo) {
                if (! Schema::hasColumn($tablo, 'firma_id')) {
                    return;
                }
                $table->dropForeign(['firma_id']);
                $table->foreign('firma_id')->references('id')->on('firmalar')->cascadeOnDelete();
            });
        }
    }
};
