<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restoran_salonlari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('sube_id')->nullable()->constrained('subeler')->nullOnDelete();
            $table->string('ad');
            $table->string('kod', 64)->nullable();
            $table->boolean('aktif_mi')->default(true)->index();
            $table->unsignedInteger('siralama')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['firma_id', 'kod']);
            $table->index(['firma_id', 'sube_id', 'aktif_mi']);
        });

        Schema::create('restoran_masalari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('sube_id')->nullable()->constrained('subeler')->nullOnDelete();
            $table->foreignId('salon_id')->nullable()->constrained('restoran_salonlari')->nullOnDelete();
            $table->string('ad');
            $table->string('kod', 64)->nullable();
            $table->unsignedInteger('kapasite')->default(0);
            $table->string('durum', 32)->default('bos')->index();
            $table->boolean('aktif_mi')->default(true)->index();
            $table->unsignedInteger('siralama')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['firma_id', 'kod']);
            $table->index(['firma_id', 'sube_id', 'durum']);
            $table->index(['firma_id', 'salon_id', 'aktif_mi']);
        });

        Schema::create('restoran_adisyonlari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('sube_id')->nullable()->constrained('subeler')->nullOnDelete();
            $table->foreignId('masa_id')->nullable()->constrained('restoran_masalari')->nullOnDelete();
            $table->foreignId('cari_id')->nullable()->constrained('cariler')->nullOnDelete();
            $table->foreignId('garson_personel_id')->nullable()->constrained('personeller')->nullOnDelete();
            $table->foreignId('kasiyer_personel_id')->nullable()->constrained('personeller')->nullOnDelete();
            $table->foreignId('kasa_hesap_id')->nullable()->constrained('kasa_hesaplari')->nullOnDelete();
            $table->foreignId('banka_hesap_id')->nullable()->constrained('banka_hesaplari')->nullOnDelete();
            $table->foreignId('pos_hesap_id')->nullable()->constrained('pos_hesaplari')->nullOnDelete();
            $table->foreignId('finans_hareketi_id')->nullable()->constrained('finans_hareketleri')->nullOnDelete();
            $table->string('adisyon_no', 64);
            $table->dateTime('acilis_at');
            $table->dateTime('kapanis_at')->nullable();
            $table->string('durum', 32)->default('acik')->index();
            $table->string('siparis_tipi', 32)->default('masa')->index();
            $table->string('odeme_kanali', 32)->nullable();
            $table->unsignedInteger('musteri_sayisi')->default(1);
            $table->decimal('ara_toplam', 18, 2)->default(0);
            $table->decimal('indirim_toplam', 18, 2)->default(0);
            $table->decimal('kdv_toplam', 18, 2)->default(0);
            $table->decimal('genel_toplam', 18, 2)->default(0);
            $table->char('para_birimi', 3)->default('TRY');
            $table->dateTime('tahsilat_at')->nullable();
            $table->text('notlar')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['firma_id', 'adisyon_no']);
            $table->index(['firma_id', 'masa_id', 'durum'], 'rest_adisyon_masa_durum_idx');
            $table->index(['firma_id', 'garson_personel_id', 'acilis_at'], 'rest_adisyon_garson_acilis_idx');
            $table->index(['firma_id', 'kasiyer_personel_id', 'tahsilat_at'], 'rest_adisyon_kasiyer_tahsilat_idx');
            $table->index(['firma_id', 'finans_hareketi_id'], 'rest_adisyon_finans_idx');
            $table->index(['firma_id', 'durum', 'acilis_at'], 'rest_adisyon_durum_acilis_idx');
        });

        Schema::create('restoran_adisyon_kalemleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('adisyon_id')->constrained('restoran_adisyonlari')->cascadeOnDelete();
            $table->foreignId('stok_karti_id')->nullable()->constrained('stok_kartlari')->nullOnDelete();
            $table->foreignId('hazirlayan_personel_id')->nullable()->constrained('personeller')->nullOnDelete();
            $table->string('urun_adi');
            $table->decimal('miktar', 18, 4)->default(1);
            $table->decimal('birim_fiyat', 18, 2)->default(0);
            $table->decimal('kdv_orani', 5, 2)->default(0);
            $table->decimal('iskonto_tutari', 18, 2)->default(0);
            $table->decimal('ara_tutar', 18, 2)->default(0);
            $table->decimal('kdv_tutari', 18, 2)->default(0);
            $table->decimal('toplam_tutar', 18, 2)->default(0);
            $table->string('durum', 32)->default('yeni')->index();
            $table->text('mutfak_notu')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['firma_id', 'adisyon_id']);
            $table->index(['firma_id', 'stok_karti_id']);
            $table->index(['firma_id', 'durum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restoran_adisyon_kalemleri');
        Schema::dropIfExists('restoran_adisyonlari');
        Schema::dropIfExists('restoran_masalari');
        Schema::dropIfExists('restoran_salonlari');
    }
};
