<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personel_vardiya_sablonlari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('sube_id')->nullable()->constrained('subeler')->nullOnDelete();
            $table->string('ad');
            $table->time('baslangic_saati');
            $table->time('bitis_saati');
            $table->unsignedInteger('mola_dakika')->default(0);
            $table->string('renk', 30)->nullable();
            $table->boolean('aktif_mi')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['firma_id', 'sube_id', 'aktif_mi'], 'pt_vardiya_sablon_firma_sube_aktif_idx');
        });

        Schema::create('personel_vardiyalari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('sube_id')->nullable()->constrained('subeler')->nullOnDelete();
            $table->foreignId('personel_id')->constrained('personeller')->cascadeOnDelete();
            $table->foreignId('departman_id')->nullable()->constrained('personel_departmanlari')->nullOnDelete();
            $table->foreignId('vardiya_sablonu_id')->nullable()->constrained('personel_vardiya_sablonlari')->nullOnDelete();
            $table->date('tarih');
            $table->dateTime('baslangic_at');
            $table->dateTime('bitis_at');
            $table->time('baslangic_saati')->nullable();
            $table->time('bitis_saati')->nullable();
            $table->unsignedInteger('mola_dakika')->default(0);
            $table->string('vardiya_tipi', 40)->default('normal');
            $table->string('durum', 40)->default('planlandi')->index();
            $table->text('aciklama')->nullable();
            $table->text('notlar')->nullable();
            $table->foreignId('olusturan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('onaylayan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['firma_id', 'personel_id', 'tarih'], 'pt_vardiya_firma_personel_tarih_idx');
            $table->index(['firma_id', 'sube_id', 'tarih'], 'pt_vardiya_firma_sube_tarih_idx');
            $table->index(['firma_id', 'baslangic_at', 'bitis_at'], 'pt_vardiya_firma_zaman_idx');
        });

        Schema::create('personel_giris_cikislari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('sube_id')->nullable()->constrained('subeler')->nullOnDelete();
            $table->foreignId('personel_id')->constrained('personeller')->cascadeOnDelete();
            $table->foreignId('vardiya_id')->nullable()->constrained('personel_vardiyalari')->nullOnDelete();
            $table->date('tarih')->nullable();
            $table->dateTime('giris_at')->nullable();
            $table->dateTime('cikis_at')->nullable();
            $table->dateTime('giris_zamani')->nullable();
            $table->dateTime('cikis_zamani')->nullable();
            $table->string('giris_tipi', 40)->nullable();
            $table->string('cikis_tipi', 40)->nullable();
            $table->string('kayit_tipi', 40)->default('manuel');
            $table->string('kaynak', 40)->default('panel');
            $table->string('giris_ip')->nullable();
            $table->string('cikis_ip')->nullable();
            $table->string('cihaz_bilgisi')->nullable();
            $table->decimal('konum_lat', 10, 7)->nullable();
            $table->decimal('konum_lng', 10, 7)->nullable();
            $table->integer('gec_kalma_dakika')->default(0);
            $table->integer('erken_cikis_dakika')->default(0);
            $table->integer('erken_cikma_dakika')->default(0);
            $table->integer('fazla_mesai_dakika')->default(0);
            $table->integer('eksik_calisma_dakika')->default(0);
            $table->string('onay_durumu', 40)->default('onay_bekliyor')->index();
            $table->foreignId('onaylayan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('onaylayan_kullanici_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('onay_tarihi')->nullable();
            $table->text('aciklama')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['firma_id', 'personel_id', 'giris_at'], 'pt_giris_firma_personel_zaman_idx');
            $table->index(['firma_id', 'sube_id', 'giris_at'], 'pt_giris_firma_sube_zaman_idx');
            $table->index(['firma_id', 'onay_durumu'], 'pt_giris_firma_onay_idx');
        });

        Schema::create('personel_izinleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('personel_id')->constrained('personeller')->cascadeOnDelete();
            $table->string('izin_turu', 60);
            $table->date('baslangic_tarihi')->nullable();
            $table->date('bitis_tarihi')->nullable();
            $table->dateTime('baslangic_at');
            $table->dateTime('bitis_at');
            $table->decimal('gun_sayisi', 8, 2)->default(0);
            $table->decimal('saat_sayisi', 8, 2)->nullable();
            $table->string('durum', 40)->default('onay_bekliyor')->index();
            $table->string('onay_durumu', 40)->default('onay_bekliyor')->index();
            $table->foreignId('onaylayan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('onay_at')->nullable();
            $table->text('aciklama')->nullable();
            $table->string('belge_path')->nullable();
            $table->string('belge_yolu')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['firma_id', 'personel_id', 'baslangic_at', 'bitis_at'], 'pt_izin_firma_personel_zaman_idx');
            $table->index(['firma_id', 'durum'], 'pt_izin_firma_durum_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personel_izinleri');
        Schema::dropIfExists('personel_giris_cikislari');
        Schema::dropIfExists('personel_vardiyalari');
        Schema::dropIfExists('personel_vardiya_sablonlari');
    }
};
