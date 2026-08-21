<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teknik_servis_kayitlari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->restrictOnDelete();

            $table->string('servis_tipi', 32);
            $table->string('oncelik', 16)->default('normal');
            $table->string('servis_kanali', 24)->default('magaza');

            $table->foreignId('cari_id')->constrained('cariler')->restrictOnDelete();
            $table->foreignId('cihaz_id')->nullable()->constrained('teknik_servis_tanim_cihazlar')->nullOnDelete();
            $table->foreignId('marka_id')->nullable()->constrained('teknik_servis_tanim_markalar')->nullOnDelete();
            $table->foreignId('ariza_id')->nullable()->constrained('teknik_servis_tanim_arizalar')->nullOnDelete();

            $table->string('model_no', 128)->nullable();
            $table->string('seri_no', 128)->nullable();

            $table->text('musteri_sikayeti');
            $table->text('ic_servis_notu')->nullable();
            $table->text('musteriye_gorunen_not')->nullable();

            $table->dateTime('kabul_tarihi');
            $table->string('fis_no', 64);
            $table->unique('fis_no', 'teknik_servis_kayitlari_fis_no_unique');

            $table->date('garanti_baslangic_tarihi')->nullable();
            $table->date('garanti_bitis_tarihi')->nullable();

            $table->decimal('teklif_tutari', 18, 2)->nullable();
            $table->dateTime('teklif_tarihi')->nullable();
            $table->string('musteri_onay_durumu', 32)->default('beklemede');
            $table->text('onay_notu')->nullable();

            $table->dateTime('teslim_tarihi')->nullable();
            $table->foreignId('teslim_eden_kullanici_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('teslim_alan_ad_soyad', 191)->nullable();
            $table->string('teslim_alan_tel', 64)->nullable();
            $table->text('teslim_notu')->nullable();

            $table->text('iptal_nedeni')->nullable();
            $table->text('iade_nedeni')->nullable();

            $table->foreignId('servis_durumu_id')->constrained('teknik_servis_tanim_servis_durumlari')->restrictOnDelete();

            $table->decimal('toplam_tutar', 18, 2)->default(0);
            $table->decimal('odenen_tutar', 18, 2)->default(0);
            $table->string('odeme_durumu', 32)->default('odenmedi');

            $table->foreignId('olusturan_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('guncelleyen_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['firma_id', 'seri_no'], 'teknik_servis_kayitlari_firma_seri_no_index');
            $table->index(['firma_id', 'servis_durumu_id'], 'teknik_servis_kayitlari_firma_durum_index');
            $table->index(['firma_id', 'musteri_onay_durumu'], 'teknik_servis_kayitlari_firma_onay_index');
            $table->index(['firma_id', 'kabul_tarihi']);
            $table->index(['firma_id', 'servis_tipi']);
            $table->index(['firma_id', 'cari_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teknik_servis_kayitlari');
    }
};
