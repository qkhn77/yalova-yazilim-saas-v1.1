<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teknik_servis_tahsilatlari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar');
            $table->foreignId('teknik_servis_kaydi_id')->constrained('teknik_servis_kayitlari');
            $table->foreignId('satis_faturasi_id')->nullable()->constrained('faturalar');
            $table->foreignId('finans_hareketi_id')->nullable()->constrained('finans_hareketleri');
            $table->foreignId('iptal_finans_hareketi_id')->nullable()->constrained('finans_hareketleri');
            $table->string('kanal', 20);
            $table->foreignId('kasa_hesap_id')->nullable()->constrained('kasa_hesaplari');
            $table->foreignId('banka_hesap_id')->nullable()->constrained('banka_hesaplari');
            $table->foreignId('pos_hesap_id')->nullable()->constrained('pos_hesaplari');
            $table->string('kaynak_para_birimi', 3);
            $table->string('hedef_para_birimi', 3)->nullable();
            $table->string('doviz_kuru_turu', 20)->nullable();
            $table->decimal('doviz_kuru', 18, 8)->nullable();
            $table->decimal('tutar', 18, 2);
            $table->decimal('hedef_tutar', 18, 2)->nullable();
            $table->dateTime('tarih');
            $table->text('aciklama')->nullable();
            $table->string('durum', 20)->default('aktif');
            $table->foreignId('olusturan_id')->nullable()->constrained('users');
            $table->foreignId('guncelleyen_id')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['firma_id', 'teknik_servis_kaydi_id', 'durum'], 'ts_tahsilat_kayit_durum_idx');
            $table->index(['firma_id', 'satis_faturasi_id'], 'ts_tahsilat_fatura_idx');
            $table->index(['firma_id', 'finans_hareketi_id'], 'ts_tahsilat_finans_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teknik_servis_tahsilatlari');
    }
};
