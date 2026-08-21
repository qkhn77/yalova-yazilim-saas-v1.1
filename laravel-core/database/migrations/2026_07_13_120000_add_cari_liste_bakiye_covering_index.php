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
            // Cari listesinde bakiye alt sorgusunu covering index ile hızlandırır.
            $table->index(
                ['firma_id', 'cari_id', 'para_birimi', 'durum', 'borc', 'alacak'],
                'cari_hrk_liste_bakiye_cover_idx'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cari_hareketleri')) {
            return;
        }

        Schema::table('cari_hareketleri', function (Blueprint $table): void {
            $table->dropIndex('cari_hrk_liste_bakiye_cover_idx');
        });
    }
};
