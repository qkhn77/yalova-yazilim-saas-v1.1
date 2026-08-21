<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cekler', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->string('turu', 16);
            $table->string('durum', 16)->default('portfoyde');
            $table->string('cek_no', 80);
            $table->string('banka_adi', 160)->nullable();
            $table->string('sube_adi', 160)->nullable();
            $table->decimal('tutar', 18, 2);
            $table->char('para_birimi', 3)->default('TRY');
            $table->date('keside_tarihi')->nullable();
            $table->date('vade_tarihi')->nullable();
            $table->foreignId('sorumlu_kullanici_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('olusturan_kullanici_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('aciklama')->nullable();
            $table->timestamps();

            $table->index(['firma_id', 'durum']);
            $table->index(['firma_id', 'vade_tarihi']);
            $table->index(['firma_id', 'cek_no']);
        });

        Schema::create('cek_hareketleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('cek_id')->constrained('cekler')->cascadeOnDelete();
            $table->string('islem_turu', 16);
            $table->foreignId('cari_id')->constrained('cariler')->restrictOnDelete();
            $table->foreignId('finans_hareket_id')->nullable()->unique()->constrained('finans_hareketleri')->nullOnDelete();
            $table->foreignId('islem_yapan_kullanici_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('islem_tarihi');
            $table->decimal('tutar', 18, 2);
            $table->char('para_birimi', 3)->default('TRY');
            $table->string('idempotency_key', 191)->unique();
            $table->string('durum', 16)->default('aktif');
            $table->foreignId('iptal_edilen_hareket_id')->nullable()->constrained('cek_hareketleri')->nullOnDelete();
            $table->text('aciklama')->nullable();
            $table->timestamps();

            $table->unique(['cek_id', 'islem_turu'], 'cek_hareketleri_cek_turu_unique');
            $table->index(['firma_id', 'cari_id']);
            $table->index(['firma_id', 'islem_tarihi']);
            $table->index(['firma_id', 'durum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cek_hareketleri');
        Schema::dropIfExists('cekler');
    }
};
