<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sekreter_gorevleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('olusturan_kullanici_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('atanan_kullanici_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('atanan_personel_id')->nullable()->constrained('personeller')->nullOnDelete();
            $table->foreignId('cari_id')->nullable()->constrained('cariler')->nullOnDelete();
            $table->string('baslik');
            $table->text('aciklama')->nullable();
            $table->date('tarih');
            $table->time('saat')->nullable();
            $table->string('durum', 24)->default('bekliyor');
            $table->string('oncelik', 16)->default('normal');
            $table->string('hatirlatma_tipi', 24)->default('yok');
            $table->string('tekrar_tipi', 16)->default('yok');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['firma_id', 'tarih', 'durum']);
            $table->index(['firma_id', 'atanan_kullanici_id']);
        });

        Schema::create('sekreter_randevulari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('olusturan_kullanici_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('cari_id')->nullable()->constrained('cariler')->nullOnDelete();
            $table->string('baslik');
            $table->date('baslangic_tarihi');
            $table->time('baslangic_saati');
            $table->date('bitis_tarihi');
            $table->time('bitis_saati');
            $table->text('aciklama')->nullable();
            $table->string('hatirlatma_tipi', 24)->default('yok');
            $table->string('tekrar_tipi', 16)->default('yok');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['firma_id', 'baslangic_tarihi']);
        });

        Schema::create('sekreter_notlari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('kullanici_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('cari_id')->nullable()->constrained('cariler')->nullOnDelete();
            $table->string('baslik');
            $table->longText('icerik');
            $table->string('etiket')->nullable();
            $table->boolean('sabit_mi')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['firma_id', 'sabit_mi']);
        });

        Schema::create('sekreter_hatirlatmalari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->string('hatirlanabilir_type');
            $table->unsignedBigInteger('hatirlanabilir_id');
            $table->string('hatirlatma_tipi', 24);
            $table->dateTime('hatirlatma_zamani')->nullable();
            $table->dateTime('gonderildi_at')->nullable();
            $table->dateTime('okundu_at')->nullable();
            $table->timestamps();
            $table->index(['hatirlanabilir_type', 'hatirlanabilir_id'], 'sekreter_hatirlanabilir_index');
            $table->index(['firma_id', 'hatirlatma_zamani', 'gonderildi_at'], 'sekreter_hatirlatma_zaman_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sekreter_hatirlatmalari');
        Schema::dropIfExists('sekreter_notlari');
        Schema::dropIfExists('sekreter_randevulari');
        Schema::dropIfExists('sekreter_gorevleri');
    }
};
