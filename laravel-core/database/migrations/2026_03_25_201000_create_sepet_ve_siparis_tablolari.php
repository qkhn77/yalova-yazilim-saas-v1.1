<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sepetler', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kullanici_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('oturum_id', 128)->nullable()->index();
            $table->timestamp('son_aktif_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sepet_kalemleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sepet_id')->constrained('sepetler')->cascadeOnDelete();
            $table->foreignId('stok_karti_id')->constrained('stok_kartlari')->restrictOnDelete();
            $table->string('urun_adi_snapshot', 255);
            $table->string('urun_kodu_snapshot', 100)->nullable();
            $table->decimal('birim_fiyat', 12, 2);
            $table->decimal('kdv_orani', 5, 2)->default(0);
            $table->decimal('miktar', 12, 4)->default(1);
            $table->decimal('satir_toplami', 14, 2);
            $table->timestamps();

            $table->unique(['sepet_id', 'stok_karti_id'], 'sepet_stok_unique');
        });

        Schema::create('siparisler', function (Blueprint $table): void {
            $table->id();
            $table->string('siparis_no', 32)->unique();
            $table->foreignId('firma_id')->nullable()->constrained('firmalar')->nullOnDelete();
            $table->foreignId('kullanici_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('musteri_ad_soyad', 255);
            $table->string('musteri_email', 255)->nullable();
            $table->string('musteri_telefon', 50);
            $table->text('teslimat_adresi');
            $table->text('notlar')->nullable();
            $table->string('para_birimi', 3)->default('TRY');
            $table->decimal('ara_toplam', 14, 2)->default(0);
            $table->decimal('kdv_toplam', 14, 2)->default(0);
            $table->decimal('genel_toplam', 14, 2)->default(0);
            $table->string('durum', 32)->index();
            $table->timestamps();
        });

        Schema::create('siparis_kalemleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('siparis_id')->constrained('siparisler')->cascadeOnDelete();
            $table->foreignId('stok_karti_id')->constrained('stok_kartlari')->restrictOnDelete();
            $table->string('urun_adi_snapshot', 255);
            $table->string('urun_kodu_snapshot', 100)->nullable();
            $table->decimal('miktar', 12, 4);
            $table->decimal('birim_fiyat', 12, 2);
            $table->decimal('kdv_orani', 5, 2)->default(0);
            $table->decimal('satir_toplami', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siparis_kalemleri');
        Schema::dropIfExists('siparisler');
        Schema::dropIfExists('sepet_kalemleri');
        Schema::dropIfExists('sepetler');
    }
};
