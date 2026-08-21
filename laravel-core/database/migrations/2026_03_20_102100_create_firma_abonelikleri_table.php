<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firma_abonelikleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('planlar')->restrictOnDelete();
            $table->enum('durum', ['aktif', 'suresi_doldu', 'iptal'])->default('aktif')->index();
            $table->date('baslangic_tarihi');
            $table->date('bitis_tarihi')->index();
            $table->boolean('otomatik_yenileme')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['firma_id', 'durum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firma_abonelikleri');
    }
};

