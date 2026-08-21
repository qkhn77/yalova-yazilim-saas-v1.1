<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('muhasebe_alacak_planlari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('cari_id')->constrained('cariler')->restrictOnDelete();
            $table->string('kaynak_turu', 64);
            $table->unsignedBigInteger('kaynak_id')->nullable();
            $table->string('plan_turu', 32);
            $table->decimal('toplam_tutar', 18, 2)->default(0);
            $table->decimal('pesinat_tutari', 18, 2)->default(0);
            $table->decimal('planlanan_tutar', 18, 2)->default(0);
            $table->decimal('odenen_tutar', 18, 2)->default(0);
            $table->decimal('kalan_tutar', 18, 2)->default(0);
            $table->char('para_birimi', 3)->default('TRY');
            $table->date('baslangic_tarihi')->nullable();
            $table->date('son_vade_tarihi')->nullable();
            $table->string('durum', 32)->default('aktif');
            $table->text('aciklama')->nullable();
            $table->foreignId('olusturan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['firma_id', 'cari_id', 'durum'], 'muh_alacak_plan_firma_cari_durum_idx');
            $table->index(['firma_id', 'kaynak_turu', 'kaynak_id'], 'muh_alacak_plan_kaynak_idx');
            $table->index(['firma_id', 'son_vade_tarihi'], 'muh_alacak_plan_son_vade_idx');
        });

        Schema::create('muhasebe_alacak_plan_taksitleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('alacak_plan_id')->constrained('muhasebe_alacak_planlari')->cascadeOnDelete();
            $table->foreignId('cari_id')->constrained('cariler')->restrictOnDelete();
            $table->foreignId('cari_hareket_id')->nullable()->constrained('cari_hareketleri')->nullOnDelete();
            $table->unsignedSmallInteger('sira_no')->default(1);
            $table->date('vade_tarihi');
            $table->decimal('tutar', 18, 2)->default(0);
            $table->decimal('odenen_tutar', 18, 2)->default(0);
            $table->decimal('kalan_tutar', 18, 2)->default(0);
            $table->dateTime('son_tahsilat_tarihi')->nullable();
            $table->string('durum', 32)->default('bekliyor');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['alacak_plan_id', 'sira_no'], 'muh_alacak_taksit_plan_sira_uq');
            $table->index(['firma_id', 'cari_id', 'durum'], 'muh_alacak_taksit_firma_cari_durum_idx');
            $table->index(['firma_id', 'vade_tarihi', 'durum'], 'muh_alacak_taksit_vade_durum_idx');
        });

        Schema::create('muhasebe_alacak_tahsilat_eslesmeleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->unsignedBigInteger('alacak_plan_id');
            $table->unsignedBigInteger('alacak_plan_taksiti_id');
            $table->unsignedBigInteger('finans_hareketi_id');
            $table->decimal('tutar', 18, 2)->default(0);
            $table->dateTime('tarih');
            $table->timestamps();

            $table->foreign('alacak_plan_id', 'muh_alacak_eslesme_plan_fk')
                ->references('id')
                ->on('muhasebe_alacak_planlari')
                ->cascadeOnDelete();
            $table->foreign('alacak_plan_taksiti_id', 'muh_alacak_eslesme_taksit_fk')
                ->references('id')
                ->on('muhasebe_alacak_plan_taksitleri')
                ->cascadeOnDelete();
            $table->foreign('finans_hareketi_id', 'muh_alacak_eslesme_finans_fk')
                ->references('id')
                ->on('finans_hareketleri')
                ->cascadeOnDelete();
            $table->index(['firma_id', 'finans_hareketi_id'], 'muh_alacak_eslesme_finans_idx');
            $table->index(['firma_id', 'alacak_plan_taksiti_id'], 'muh_alacak_eslesme_taksit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('muhasebe_alacak_tahsilat_eslesmeleri');
        Schema::dropIfExists('muhasebe_alacak_plan_taksitleri');
        Schema::dropIfExists('muhasebe_alacak_planlari');
    }
};
