<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_transferleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->string('transfer_no', 64);
            $table->foreignId('kaynak_depo_id')->constrained('muhasebe_depolar')->restrictOnDelete();
            $table->foreignId('hedef_depo_id')->constrained('muhasebe_depolar')->restrictOnDelete();
            $table->dateTime('tarih');
            $table->string('durum', 32)->default('tamamlandi');
            $table->text('aciklama')->nullable();
            $table->timestamps();

            $table->unique(['firma_id', 'transfer_no']);
            $table->index(['firma_id', 'tarih']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_transferleri');
    }
};
