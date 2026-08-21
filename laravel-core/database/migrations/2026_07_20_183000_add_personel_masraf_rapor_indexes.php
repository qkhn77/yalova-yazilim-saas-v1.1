<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('personel_maas_donemleri') && ! Schema::hasIndex('personel_maas_donemleri', 'pm_donem_rapor_tarih_idx')) {
            Schema::table('personel_maas_donemleri', function (Blueprint $table): void {
                $table->index(
                    ['firma_id', 'baslangic_tarihi', 'bitis_tarihi'],
                    'pm_donem_rapor_tarih_idx'
                );
            });
        }

        if (Schema::hasTable('personel_avanslari') && ! Schema::hasIndex('personel_avanslari', 'pa_masraf_rapor_tarih_idx')) {
            Schema::table('personel_avanslari', function (Blueprint $table): void {
                $table->index(
                    ['firma_id', 'tarih', 'onay_durumu'],
                    'pa_masraf_rapor_tarih_idx'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('personel_maas_donemleri') && Schema::hasIndex('personel_maas_donemleri', 'pm_donem_rapor_tarih_idx')) {
            Schema::table('personel_maas_donemleri', function (Blueprint $table): void {
                $table->dropIndex('pm_donem_rapor_tarih_idx');
            });
        }

        if (Schema::hasTable('personel_avanslari') && Schema::hasIndex('personel_avanslari', 'pa_masraf_rapor_tarih_idx')) {
            Schema::table('personel_avanslari', function (Blueprint $table): void {
                $table->dropIndex('pa_masraf_rapor_tarih_idx');
            });
        }
    }
};
