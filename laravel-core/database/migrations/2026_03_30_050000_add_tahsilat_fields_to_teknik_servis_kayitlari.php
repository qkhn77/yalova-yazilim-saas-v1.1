<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teknik_servis_kayitlari')) {
            return;
        }

        Schema::table('teknik_servis_kayitlari', function (Blueprint $table): void {
            if (! Schema::hasColumn('teknik_servis_kayitlari', 'tahsilat_kanali')) {
                $table->string('tahsilat_kanali', 16)->nullable()->after('odeme_durumu');
            }

            if (! Schema::hasColumn('teknik_servis_kayitlari', 'tahsilat_kasa_hesap_id')) {
                $table->unsignedBigInteger('tahsilat_kasa_hesap_id')->nullable()->after('tahsilat_kanali');
            }

            if (! Schema::hasColumn('teknik_servis_kayitlari', 'tahsilat_banka_hesap_id')) {
                $table->unsignedBigInteger('tahsilat_banka_hesap_id')->nullable()->after('tahsilat_kasa_hesap_id');
            }

            if (! Schema::hasColumn('teknik_servis_kayitlari', 'tahsilat_pos_hesap_id')) {
                $table->unsignedBigInteger('tahsilat_pos_hesap_id')->nullable()->after('tahsilat_banka_hesap_id');
            }

            if (! Schema::hasColumn('teknik_servis_kayitlari', 'tahsilat_para_birimi')) {
                $table->char('tahsilat_para_birimi', 3)->nullable()->after('tahsilat_pos_hesap_id');
            }

            if (! Schema::hasColumn('teknik_servis_kayitlari', 'tahsilat_tutari')) {
                $table->decimal('tahsilat_tutari', 18, 2)->nullable()->after('tahsilat_para_birimi');
            }

            if (! Schema::hasColumn('teknik_servis_kayitlari', 'tahsilat_tarihi')) {
                $table->dateTime('tahsilat_tarihi')->nullable()->after('tahsilat_tutari');
            }

            if (! Schema::hasColumn('teknik_servis_kayitlari', 'tahsilat_aciklama')) {
                $table->text('tahsilat_aciklama')->nullable()->after('tahsilat_tarihi');
            }
        });

        Schema::table('teknik_servis_kayitlari', function (Blueprint $table): void {
            $table->foreign('tahsilat_kasa_hesap_id', 'ts_kayit_tahsilat_kasa_fk')->references('id')->on('kasa_hesaplari')->nullOnDelete();
            $table->foreign('tahsilat_banka_hesap_id', 'ts_kayit_tahsilat_banka_fk')->references('id')->on('banka_hesaplari')->nullOnDelete();
            $table->foreign('tahsilat_pos_hesap_id', 'ts_kayit_tahsilat_pos_fk')->references('id')->on('pos_hesaplari')->nullOnDelete();

            $table->index(['firma_id', 'tahsilat_kanali'], 'ts_kayit_firma_tahsilat_kanal_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('teknik_servis_kayitlari')) {
            return;
        }

        Schema::table('teknik_servis_kayitlari', function (Blueprint $table): void {
            if (Schema::hasColumn('teknik_servis_kayitlari', 'tahsilat_kasa_hesap_id')) {
                $table->dropForeign('ts_kayit_tahsilat_kasa_fk');
            }
            if (Schema::hasColumn('teknik_servis_kayitlari', 'tahsilat_banka_hesap_id')) {
                $table->dropForeign('ts_kayit_tahsilat_banka_fk');
            }
            if (Schema::hasColumn('teknik_servis_kayitlari', 'tahsilat_pos_hesap_id')) {
                $table->dropForeign('ts_kayit_tahsilat_pos_fk');
            }
            $table->dropIndex('ts_kayit_firma_tahsilat_kanal_idx');
        });

        Schema::table('teknik_servis_kayitlari', function (Blueprint $table): void {
            $table->dropColumn([
                'tahsilat_kanali',
                'tahsilat_kasa_hesap_id',
                'tahsilat_banka_hesap_id',
                'tahsilat_pos_hesap_id',
                'tahsilat_para_birimi',
                'tahsilat_tutari',
                'tahsilat_tarihi',
                'tahsilat_aciklama',
            ]);
        });
    }
};