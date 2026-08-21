<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personel_avanslari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('personel_id')->constrained('personeller')->cascadeOnDelete();
            $table->date('tarih');
            $table->decimal('tutar', 15, 2);
            $table->string('para_birimi', 3)->default('TRY');
            $table->string('odeme_kanali', 40)->nullable();
            $table->string('odeme_kaynagi', 40)->nullable();
            $table->unsignedBigInteger('kasa_hesap_id')->nullable();
            $table->unsignedBigInteger('kasa_hesabi_id')->nullable();
            $table->unsignedBigInteger('banka_hesap_id')->nullable();
            $table->unsignedBigInteger('banka_hesabi_id')->nullable();
            $table->unsignedBigInteger('pos_hesap_id')->nullable();
            $table->string('durum', 40)->default('taslak')->index();
            $table->string('mahsup_durumu', 40)->default('bekliyor')->index();
            $table->string('onay_durumu', 40)->default('bekliyor')->index();
            $table->foreignId('onaylayan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('onaylayan_kullanici_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('onay_tarihi')->nullable();
            $table->unsignedBigInteger('finans_hareketi_id')->nullable();
            $table->text('aciklama')->nullable();
            $table->boolean('maastan_dusuldu_mu')->default(false)->index();
            $table->decimal('kalan_tutar', 15, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['firma_id', 'personel_id', 'tarih']);
            $table->index(['firma_id', 'durum']);
            $table->index(['firma_id', 'finans_hareketi_id']);
        });

        Schema::create('personel_maas_donemleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('sube_id')->nullable()->constrained('subeler')->nullOnDelete();
            $table->string('ad')->nullable();
            $table->unsignedSmallInteger('donem_yil')->nullable();
            $table->unsignedTinyInteger('donem_ay')->nullable();
            $table->date('baslangic_tarihi');
            $table->date('bitis_tarihi');
            $table->string('durum', 40)->default('taslak')->index();
            $table->decimal('toplam_brut', 15, 2)->default(0);
            $table->decimal('toplam_kesinti', 15, 2)->default(0);
            $table->decimal('toplam_net', 15, 2)->default(0);
            $table->string('para_birimi', 3)->default('TRY');
            $table->text('aciklama')->nullable();
            $table->foreignId('olusturan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('onaylayan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('onay_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['firma_id', 'sube_id', 'donem_yil', 'donem_ay'], 'personel_maas_donem_unique');
            $table->index(['firma_id', 'durum']);
        });

        Schema::create('personel_maas_hareketleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('maas_donemi_id')->constrained('personel_maas_donemleri')->cascadeOnDelete();
            $table->foreignId('personel_id')->constrained('personeller')->cascadeOnDelete();
            $table->decimal('brut_tutar', 15, 2)->default(0);
            $table->decimal('fazla_mesai_tutari', 15, 2)->default(0);
            $table->decimal('prim_tutari', 15, 2)->default(0);
            $table->decimal('ek_odeme_tutari', 15, 2)->default(0);
            $table->decimal('avans_kesintisi', 15, 2)->default(0);
            $table->decimal('devamsizlik_kesintisi', 15, 2)->default(0);
            $table->decimal('diger_kesinti', 15, 2)->default(0);
            $table->decimal('net_tutar', 15, 2)->default(0);
            $table->decimal('odenen_tutar', 15, 2)->default(0);
            $table->decimal('kalan_tutar', 15, 2)->default(0);
            $table->string('durum', 40)->default('taslak')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['firma_id', 'maas_donemi_id', 'personel_id'], 'personel_maas_hareket_unique');
            $table->index(['firma_id', 'personel_id']);
        });

        Schema::create('personel_maas_kalemleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('maas_hareketi_id')->constrained('personel_maas_hareketleri')->cascadeOnDelete();
            $table->string('kalem_turu', 60);
            $table->string('aciklama')->nullable();
            $table->decimal('tutar', 15, 2);
            $table->timestamps();

            $table->index(['firma_id', 'maas_hareketi_id']);
        });

        Schema::create('personel_maas_odeme_kayitlari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('maas_hareketi_id')->constrained('personel_maas_hareketleri')->cascadeOnDelete();
            $table->date('tarih');
            $table->decimal('tutar', 15, 2);
            $table->string('para_birimi', 3)->default('TRY');
            $table->string('odeme_kanali', 40)->nullable();
            $table->unsignedBigInteger('kasa_hesap_id')->nullable();
            $table->unsignedBigInteger('banka_hesap_id')->nullable();
            $table->unsignedBigInteger('finans_hareketi_id')->nullable();
            $table->text('aciklama')->nullable();
            $table->timestamps();

            $table->index(['firma_id', 'maas_hareketi_id']);
            $table->index(['firma_id', 'finans_hareketi_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personel_maas_odeme_kayitlari');
        Schema::dropIfExists('personel_maas_kalemleri');
        Schema::dropIfExists('personel_maas_hareketleri');
        Schema::dropIfExists('personel_maas_donemleri');
        Schema::dropIfExists('personel_avanslari');
    }
};
