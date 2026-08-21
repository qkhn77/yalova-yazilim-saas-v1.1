<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kullanici_mesaj_katilimcilari')) {
            Schema::table('kullanici_mesaj_katilimcilari', function (Blueprint $table): void {
                $table->index(['kullanici_id', 'favori_mi', 'arsivlendi_mi'], 'kmm_katilimci_favori_arsiv_idx');
                $table->index(['kullanici_id', 'sessize_alindi_mi'], 'kmm_katilimci_sessiz_idx');
                $table->index(['konu_id', 'son_okuma_at'], 'kmm_katilimci_okuma_idx');
            });
        }

        if (Schema::hasTable('kullanici_mesaj_konulari')) {
            Schema::table('kullanici_mesaj_konulari', function (Blueprint $table): void {
                $table->index(['firma_id', 'durum', 'son_mesaj_at'], 'kmm_konu_firma_durum_son_idx');
                $table->index(['firma_id', 'oncelik', 'son_mesaj_at'], 'kmm_konu_firma_oncelik_son_idx');
            });
        }

        if (Schema::hasTable('kullanici_bildirimleri')) {
            Schema::table('kullanici_bildirimleri', function (Blueprint $table): void {
                $table->index(['kullanici_id', 'firma_id', 'okundu_at', 'created_at'], 'kmm_bildirim_kullanici_okundu_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('kullanici_bildirimleri')) {
            Schema::table('kullanici_bildirimleri', function (Blueprint $table): void {
                $table->dropIndex('kmm_bildirim_kullanici_okundu_idx');
            });
        }

        if (Schema::hasTable('kullanici_mesaj_konulari')) {
            Schema::table('kullanici_mesaj_konulari', function (Blueprint $table): void {
                $table->dropIndex('kmm_konu_firma_durum_son_idx');
                $table->dropIndex('kmm_konu_firma_oncelik_son_idx');
            });
        }

        if (Schema::hasTable('kullanici_mesaj_katilimcilari')) {
            Schema::table('kullanici_mesaj_katilimcilari', function (Blueprint $table): void {
                $table->dropIndex('kmm_katilimci_favori_arsiv_idx');
                $table->dropIndex('kmm_katilimci_sessiz_idx');
                $table->dropIndex('kmm_katilimci_okuma_idx');
            });
        }
    }
};
