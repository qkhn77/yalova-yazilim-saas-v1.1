<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restoran_adisyon_tahsilatlari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('adisyon_id')->constrained('restoran_adisyonlari')->cascadeOnDelete();
            $table->foreignId('finans_hareketi_id')->constrained('finans_hareketleri')->cascadeOnDelete();
            $table->foreignId('kasa_hesap_id')->nullable()->constrained('kasa_hesaplari')->nullOnDelete();
            $table->foreignId('banka_hesap_id')->nullable()->constrained('banka_hesaplari')->nullOnDelete();
            $table->foreignId('pos_hesap_id')->nullable()->constrained('pos_hesaplari')->nullOnDelete();
            $table->string('odeme_kanali', 32);
            $table->decimal('tutar', 18, 2);
            $table->char('para_birimi', 3)->default('TRY');
            $table->dateTime('tahsilat_at');
            $table->string('durum', 32)->default('aktif')->index();
            $table->text('notlar')->nullable();
            $table->timestamps();

            $table->unique('finans_hareketi_id', 'restoran_tahsilat_finans_unique');
            $table->index(['firma_id', 'adisyon_id', 'durum'], 'restoran_tahsilat_adisyon_idx');
            $table->index(['firma_id', 'odeme_kanali', 'tahsilat_at'], 'restoran_tahsilat_kanal_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restoran_adisyon_tahsilatlari');
    }
};
