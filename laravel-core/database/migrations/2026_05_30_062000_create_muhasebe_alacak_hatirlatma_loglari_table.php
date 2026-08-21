<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('muhasebe_alacak_hatirlatma_loglari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->restrictOnDelete();
            $table->foreignId('cari_id')->nullable()->constrained('cariler')->nullOnDelete();
            $table->string('kanal', 30);
            $table->string('saglayici', 60)->nullable();
            $table->string('hedef', 191)->nullable();
            $table->string('baslik', 191)->nullable();
            $table->text('mesaj')->nullable();
            $table->string('durum', 30)->default('kuyrukta');
            $table->unsignedSmallInteger('deneme_sayisi')->default(0);
            $table->timestamp('son_deneme_at')->nullable();
            $table->timestamp('gonderildi_at')->nullable();
            $table->text('hata')->nullable();
            $table->json('payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['firma_id', 'kanal', 'durum'], 'alacak_hatirlatma_firma_kanal_durum_idx');
            $table->index(['firma_id', 'cari_id', 'created_at'], 'alacak_hatirlatma_cari_tarih_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('muhasebe_alacak_hatirlatma_loglari');
    }
};
