<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firma_modulleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('modul_id')->constrained('moduller')->cascadeOnDelete();
            $table->enum('durum', ['aktif', 'salt_okunur', 'kapali'])->default('kapali')->index();
            $table->date('baslangic_tarihi')->nullable();
            $table->date('bitis_tarihi')->nullable()->index();
            $table->timestamps();

            $table->unique(['firma_id', 'modul_id']);
            $table->index(['firma_id', 'durum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firma_modulleri');
    }
};

