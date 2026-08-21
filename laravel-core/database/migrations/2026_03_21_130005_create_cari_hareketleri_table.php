<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cari_hareketleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('cari_id')->constrained('cariler')->restrictOnDelete();
            $table->string('belge_turu', 32);
            $table->unsignedBigInteger('belge_id');
            $table->dateTime('islem_tarihi');
            $table->date('vade_tarihi')->nullable();
            $table->decimal('borc', 18, 2)->default(0);
            $table->decimal('alacak', 18, 2)->default(0);
            $table->char('para_birimi', 3)->default('TRY');
            $table->text('aciklama')->nullable();
            $table->string('durum', 32)->default('aktif');
            $table->foreignId('iptal_edilen_hareket_id')->nullable()->constrained('cari_hareketleri')->nullOnDelete();
            $table->timestamps();

            $table->index(['firma_id', 'cari_id']);
            $table->index(['firma_id', 'belge_turu', 'belge_id']);
            $table->index(['firma_id', 'islem_tarihi']);
            $table->index(['firma_id', 'durum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cari_hareketleri');
    }
};
