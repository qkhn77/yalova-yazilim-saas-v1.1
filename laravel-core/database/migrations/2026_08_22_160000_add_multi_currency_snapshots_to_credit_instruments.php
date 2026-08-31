<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kur/baz alanları nullable bırakılır: eski kayıtların hangi kurla
     * oluşturulduğu geriye dönük olarak güvenilir biçimde bilinemez.
     */
    public function up(): void
    {
        foreach ([
            'muhasebe_alacak_planlari',
            'muhasebe_alacak_plan_taksitleri',
            'muhasebe_alacak_tahsilat_eslesmeleri',
            'cekler',
            'cek_hareketleri',
            'senetler',
            'senet_hareketleri',
        ] as $tablo) {
            if (! Schema::hasTable($tablo)) {
                continue;
            }

            Schema::table($tablo, function (Blueprint $table): void {
                if (! Schema::hasColumn($table->getTable(), 'kur')) {
                    $table->decimal('kur', 18, 8)->nullable();
                }
                if (! Schema::hasColumn($table->getTable(), 'baz_para_birimi')) {
                    $table->char('baz_para_birimi', 3)->nullable();
                }
                if (! Schema::hasColumn($table->getTable(), 'baz_tutar')) {
                    $table->decimal('baz_tutar', 18, 2)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'muhasebe_alacak_planlari',
            'muhasebe_alacak_plan_taksitleri',
            'muhasebe_alacak_tahsilat_eslesmeleri',
            'cekler',
            'cek_hareketleri',
            'senetler',
            'senet_hareketleri',
        ] as $tablo) {
            if (! Schema::hasTable($tablo)) {
                continue;
            }

            Schema::table($tablo, function (Blueprint $table): void {
                foreach (['kur', 'baz_para_birimi', 'baz_tutar'] as $kolon) {
                    if (Schema::hasColumn($table->getTable(), $kolon)) {
                        $table->dropColumn($kolon);
                    }
                }
            });
        }
    }
};
