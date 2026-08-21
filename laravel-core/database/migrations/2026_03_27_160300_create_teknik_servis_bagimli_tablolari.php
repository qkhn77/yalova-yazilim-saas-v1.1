<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teknik_servis_durum_gecmisleri', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->unsignedBigInteger('teknik_servis_kaydi_id');
            $table->unsignedBigInteger('onceki_servis_durumu_id')->nullable();
            $table->unsignedBigInteger('yeni_servis_durumu_id');
            $table->text('degisim_notu')->nullable();
            $table->unsignedBigInteger('degistiren_id')->nullable();
            $table->dateTime('degisim_tarihi');
            $table->timestamps();

            $table->foreign('firma_id', 'ts_durum_gecm_firma_fk')->references('id')->on('firmalar')->restrictOnDelete();
            $table->foreign('teknik_servis_kaydi_id', 'ts_durum_gecm_kayit_fk')->references('id')->on('teknik_servis_kayitlari')->restrictOnDelete();
            $table->foreign('onceki_servis_durumu_id', 'ts_durum_gecm_onceki_fk')->references('id')->on('teknik_servis_tanim_servis_durumlari')->nullOnDelete();
            $table->foreign('yeni_servis_durumu_id', 'ts_durum_gecm_yeni_fk')->references('id')->on('teknik_servis_tanim_servis_durumlari')->restrictOnDelete();
            $table->foreign('degistiren_id', 'ts_durum_gecm_user_fk')->references('id')->on('users')->nullOnDelete();

            $table->index(['firma_id', 'teknik_servis_kaydi_id'], 'ts_durum_gecm_firma_kayit_idx');
            $table->index(['firma_id', 'degisim_tarihi'], 'ts_durum_gecm_firma_tarih_idx');
        });

        Schema::create('teknik_servis_dokumanlari', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->unsignedBigInteger('teknik_servis_kaydi_id');
            $table->string('dosya_tipi', 32)->default('dokuman');
            $table->string('disk', 32);
            $table->string('yol', 512);
            $table->string('orijinal_ad', 255);
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('boyut')->nullable();
            $table->unsignedBigInteger('yukleyen_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('firma_id', 'ts_dokuman_firma_fk')->references('id')->on('firmalar')->restrictOnDelete();
            $table->foreign('teknik_servis_kaydi_id', 'ts_dokuman_kayit_fk')->references('id')->on('teknik_servis_kayitlari')->restrictOnDelete();
            $table->foreign('yukleyen_id', 'ts_dokuman_user_fk')->references('id')->on('users')->nullOnDelete();

            $table->index(['firma_id', 'teknik_servis_kaydi_id'], 'ts_dokuman_firma_kayit_idx');
        });

        Schema::create('teknik_servis_hatirlatmalari', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->unsignedBigInteger('teknik_servis_kaydi_id');
            $table->string('hatirlatma_tipi', 32)->default('bakim');
            $table->unsignedTinyInteger('periyot_ay');
            $table->date('ilk_tarih');
            $table->date('sonraki_tarih')->nullable();
            $table->string('durum', 24)->default('aktif');
            $table->text('not')->nullable();
            $table->dateTime('son_islenen_tarih')->nullable();
            $table->unsignedInteger('tekrar_sayisi')->default(0);
            $table->unsignedBigInteger('olusturan_id')->nullable();
            $table->timestamps();

            $table->foreign('firma_id', 'ts_hatirlatma_firma_fk')->references('id')->on('firmalar')->restrictOnDelete();
            $table->foreign('teknik_servis_kaydi_id', 'ts_hatirlatma_kayit_fk')->references('id')->on('teknik_servis_kayitlari')->restrictOnDelete();
            $table->foreign('olusturan_id', 'ts_hatirlatma_user_fk')->references('id')->on('users')->nullOnDelete();

            $table->index(['firma_id', 'sonraki_tarih']);
            $table->index(['firma_id', 'durum']);
        });

        Schema::create('teknik_servis_gorev_atamalari', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->unsignedBigInteger('teknik_servis_kaydi_id');
            $table->unsignedBigInteger('atanan_kullanici_id');
            $table->unsignedBigInteger('atayan_kullanici_id')->nullable();
            $table->string('rol', 32)->nullable();
            $table->dateTime('baslangic_tarihi');
            $table->dateTime('bitis_tarihi')->nullable();
            $table->string('durum', 24)->default('aktif');
            $table->text('aciklama')->nullable();
            $table->timestamps();

            $table->foreign('firma_id', 'ts_gorev_firma_fk')->references('id')->on('firmalar')->restrictOnDelete();
            $table->foreign('teknik_servis_kaydi_id', 'ts_gorev_kayit_fk')->references('id')->on('teknik_servis_kayitlari')->restrictOnDelete();
            $table->foreign('atanan_kullanici_id', 'ts_gorev_atanan_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('atayan_kullanici_id', 'ts_gorev_atayan_fk')->references('id')->on('users')->nullOnDelete();

            $table->index(['firma_id', 'teknik_servis_kaydi_id'], 'ts_gorev_firma_kayit_idx');
            $table->index(['firma_id', 'atanan_kullanici_id'], 'ts_gorev_firma_atanan_idx');
        });

        Schema::create('teknik_servis_aksesuar_kayitlari', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->unsignedBigInteger('teknik_servis_kaydi_id');
            $table->unsignedBigInteger('aksesuar_id');
            $table->decimal('adet', 10, 2)->default(1);
            $table->string('not', 255)->nullable();
            $table->timestamps();

            $table->foreign('firma_id', 'ts_aksesuar_kayit_firma_fk')->references('id')->on('firmalar')->restrictOnDelete();
            $table->foreign('teknik_servis_kaydi_id', 'ts_aksesuar_kayit_kayit_fk')->references('id')->on('teknik_servis_kayitlari')->restrictOnDelete();
            $table->foreign('aksesuar_id', 'ts_aksesuar_kayit_aksesuar_fk')->references('id')->on('teknik_servis_tanim_aksesuarlar')->restrictOnDelete();

            $table->unique(['teknik_servis_kaydi_id', 'aksesuar_id'], 'ts_aksesuar_kayit_kayit_aksesuar_uq');
            $table->index('firma_id', 'ts_aksesuar_kayit_firma_idx');
        });

        Schema::create('teknik_servis_kalemleri', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->unsignedBigInteger('teknik_servis_kaydi_id');
            $table->string('kalem_rolu', 16);
            $table->string('muhasebe_durumu', 24)->default('taslak');
            $table->string('aciklama', 255)->nullable();
            $table->unsignedBigInteger('stok_id')->nullable();
            $table->decimal('miktar', 14, 4)->default(1);
            $table->decimal('birim_fiyat', 18, 4)->default(0);
            $table->decimal('kdv_orani', 5, 2)->nullable();
            $table->boolean('kdv_dahil_mi')->default(false);
            $table->decimal('iskonto_orani', 5, 2)->nullable();
            $table->decimal('iskonto_tutari', 18, 2)->nullable();
            $table->decimal('satir_toplami', 18, 2)->default(0);
            $table->char('para_birimi', 3)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('firma_id', 'ts_kalem_firma_fk')->references('id')->on('firmalar')->restrictOnDelete();
            $table->foreign('teknik_servis_kaydi_id', 'ts_kalem_kayit_fk')->references('id')->on('teknik_servis_kayitlari')->restrictOnDelete();
            $table->foreign('stok_id', 'ts_kalem_stok_fk')->references('id')->on('stok_kartlari')->nullOnDelete();

            $table->index(['firma_id', 'teknik_servis_kaydi_id'], 'ts_kalem_firma_kayit_idx');
            $table->index(['firma_id', 'kalem_rolu'], 'ts_kalem_firma_rol_idx');
        });

        Schema::create('teknik_servis_islem_loglari', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->unsignedBigInteger('teknik_servis_kaydi_id');
            $table->string('olay_kodu', 64);
            $table->string('olay_etiketi', 191)->nullable();
            $table->json('eski_veri')->nullable();
            $table->json('yeni_veri')->nullable();
            $table->string('entity_type', 48)->default('teknik_servis_kaydi');
            $table->unsignedBigInteger('entity_id')->default(0);
            $table->text('aciklama')->nullable();
            $table->boolean('kritik_mi')->default(false);
            $table->unsignedBigInteger('kullanici_id')->nullable();
            $table->dateTime('olay_tarihi');
            $table->timestamps();

            $table->foreign('firma_id', 'ts_islem_log_firma_fk')->references('id')->on('firmalar')->restrictOnDelete();
            $table->foreign('teknik_servis_kaydi_id', 'ts_islem_log_kayit_fk')->references('id')->on('teknik_servis_kayitlari')->restrictOnDelete();
            $table->foreign('kullanici_id', 'ts_islem_log_user_fk')->references('id')->on('users')->nullOnDelete();

            $table->index(['firma_id', 'olay_kodu'], 'ts_islem_log_firma_olay_idx');
            $table->index(['firma_id', 'teknik_servis_kaydi_id'], 'ts_islem_log_firma_kayit_idx');
            $table->index(['entity_type', 'entity_id'], 'ts_islem_log_entity_idx');
        });

        Schema::create('teknik_servis_muhasebe_baglantilari', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->unsignedBigInteger('teknik_servis_kaydi_id');
            $table->string('islem_tipi', 24);
            $table->string('idempotency_key', 128);
            $table->unsignedBigInteger('satis_faturasi_id')->nullable();
            $table->unsignedBigInteger('gider_faturasi_id')->nullable();
            $table->unsignedBigInteger('finans_hareketi_id')->nullable();
            $table->dateTime('son_senkron_tarihi')->nullable();
            $table->string('senkron_durumu', 24)->default('beklemede');
            $table->text('hata_mesaji')->nullable();
            $table->timestamps();

            $table->foreign('firma_id', 'ts_muhasebe_firma_fk')->references('id')->on('firmalar')->restrictOnDelete();
            $table->foreign('teknik_servis_kaydi_id', 'ts_muhasebe_kayit_fk')->references('id')->on('teknik_servis_kayitlari')->restrictOnDelete();
            $table->foreign('satis_faturasi_id', 'ts_muhasebe_sat_fat_fk')->references('id')->on('faturalar')->nullOnDelete();
            $table->foreign('gider_faturasi_id', 'ts_muhasebe_gid_fat_fk')->references('id')->on('faturalar')->nullOnDelete();
            $table->foreign('finans_hareketi_id', 'ts_muhasebe_finans_fk')->references('id')->on('finans_hareketleri')->nullOnDelete();

            $table->unique(['firma_id', 'idempotency_key'], 'ts_muhasebe_firma_idem_uq');
            $table->index(['firma_id', 'teknik_servis_kaydi_id'], 'ts_muhasebe_firma_kayit_idx');
            $table->index(['firma_id', 'islem_tipi'], 'ts_muhasebe_firma_islem_idx');
        });

        Schema::create('teknik_servis_mesaj_loglari', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->unsignedBigInteger('teknik_servis_kaydi_id');
            $table->string('kanal', 24);
            $table->string('yon', 16)->nullable();
            $table->string('alici', 255)->nullable();
            $table->string('konu', 255)->nullable();
            $table->text('icerik_ozeti')->nullable();
            $table->string('dis_id', 128)->nullable();
            $table->string('durum', 24)->default('gonderildi');
            $table->text('hata_mesaji')->nullable();
            $table->unsignedBigInteger('gonderen_kullanici_id')->nullable();
            $table->dateTime('olay_tarihi');
            $table->timestamps();

            $table->foreign('firma_id', 'ts_mesaj_log_firma_fk')->references('id')->on('firmalar')->restrictOnDelete();
            $table->foreign('teknik_servis_kaydi_id', 'ts_mesaj_log_kayit_fk')->references('id')->on('teknik_servis_kayitlari')->restrictOnDelete();
            $table->foreign('gonderen_kullanici_id', 'ts_mesaj_log_user_fk')->references('id')->on('users')->nullOnDelete();

            $table->index(['firma_id', 'teknik_servis_kaydi_id', 'olay_tarihi'], 'ts_mesaj_log_firma_kayit_tarih_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teknik_servis_mesaj_loglari');
        Schema::dropIfExists('teknik_servis_muhasebe_baglantilari');
        Schema::dropIfExists('teknik_servis_islem_loglari');
        Schema::dropIfExists('teknik_servis_kalemleri');
        Schema::dropIfExists('teknik_servis_aksesuar_kayitlari');
        Schema::dropIfExists('teknik_servis_gorev_atamalari');
        Schema::dropIfExists('teknik_servis_hatirlatmalari');
        Schema::dropIfExists('teknik_servis_dokumanlari');
        Schema::dropIfExists('teknik_servis_durum_gecmisleri');
    }
};
