<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('muhasebe_alacak_takip_notlari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('cari_id')->constrained('cariler')->restrictOnDelete();
            $table->foreignId('alacak_plan_id')->nullable()->constrained('muhasebe_alacak_planlari')->nullOnDelete();
            $table->foreignId('alacak_plan_taksiti_id')->nullable()->constrained('muhasebe_alacak_plan_taksitleri')->nullOnDelete();
            $table->string('takip_tipi', 32)->default('not');
            $table->string('durum', 32)->default('planlandi');
            $table->dateTime('takip_tarihi')->nullable();
            $table->dateTime('sonraki_takip_tarihi')->nullable();
            $table->decimal('beklenen_tutar', 18, 2)->nullable();
            $table->char('para_birimi', 3)->default('TRY');
            $table->text('not')->nullable();
            $table->foreignId('olusturan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['firma_id', 'cari_id', 'sonraki_takip_tarihi'], 'muh_alacak_takip_cari_sonraki_idx');
            $table->index(['firma_id', 'durum', 'takip_tarihi'], 'muh_alacak_takip_durum_tarih_idx');
            $table->index(['firma_id', 'alacak_plan_taksiti_id'], 'muh_alacak_takip_taksit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('muhasebe_alacak_takip_notlari');
    }
};
