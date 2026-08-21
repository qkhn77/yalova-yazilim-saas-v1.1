<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cari_hareketleri')) {
            return;
        }

        Schema::table('cari_hareketleri', function (Blueprint $table): void {
            $table->index(
                ['firma_id', 'cari_id', 'para_birimi', 'durum', 'islem_tarihi'],
                'cari_hrk_firma_cari_para_durum_islem_idx'
            );
            $table->index(
                ['firma_id', 'cari_id', 'para_birimi', 'durum', 'vade_tarihi'],
                'cari_hrk_firma_cari_para_durum_vade_idx'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cari_hareketleri')) {
            return;
        }

        Schema::table('cari_hareketleri', function (Blueprint $table): void {
            $table->dropIndex('cari_hrk_firma_cari_para_durum_islem_idx');
            $table->dropIndex('cari_hrk_firma_cari_para_durum_vade_idx');
        });
    }
};
