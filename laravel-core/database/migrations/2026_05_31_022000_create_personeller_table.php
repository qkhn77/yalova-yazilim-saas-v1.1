<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personeller', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('sube_id')->nullable()->constrained('subeler')->nullOnDelete();
            $table->foreignId('kullanici_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('gorev_id')->nullable()->constrained('personel_gorevleri')->nullOnDelete();
            $table->foreignId('departman_id')->nullable()->constrained('personel_departmanlari')->nullOnDelete();
            $table->string('personel_no')->nullable();
            $table->string('ad_soyad');
            $table->string('ad')->nullable();
            $table->string('soyad')->nullable();
            $table->string('telefon')->nullable();
            $table->string('email')->nullable();
            $table->string('tc_kimlik_no', 20)->nullable();
            $table->text('adres')->nullable();
            $table->string('acil_durum_kisi')->nullable();
            $table->string('acil_durum_telefon')->nullable();
            $table->string('calisma_tipi', 40)->default('tam_zamanli');
            $table->string('maas_tipi', 40)->default('aylik');
            $table->decimal('maas_tutari', 15, 2)->default(0);
            $table->decimal('ucret', 15, 2)->default(0);
            $table->string('para_birimi', 3)->default('TRY');
            $table->decimal('saatlik_ucret', 15, 2)->nullable();
            $table->decimal('gunluk_ucret', 15, 2)->nullable();
            $table->date('ise_giris_tarihi')->nullable();
            $table->date('isten_cikis_tarihi')->nullable();
            $table->string('pin_kodu')->nullable();
            $table->string('durum', 40)->default('aktif')->index();
            $table->text('notlar')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['firma_id', 'personel_no']);
            $table->index(['firma_id', 'sube_id', 'durum']);
            $table->index(['firma_id', 'departman_id']);
            $table->index(['firma_id', 'gorev_id']);
            $table->index(['firma_id', 'kullanici_id']);
        });

        Schema::create('personel_belgeleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('personel_id')->constrained('personeller')->cascadeOnDelete();
            $table->string('belge_turu', 80);
            $table->string('ad');
            $table->string('dosya_yolu');
            $table->text('aciklama')->nullable();
            $table->timestamps();

            $table->index(['firma_id', 'personel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personel_belgeleri');
        Schema::dropIfExists('personeller');
    }
};
