<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('muhasebe_alacak_plan_onay_talepleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('alacak_plan_id')->constrained('muhasebe_alacak_planlari')->cascadeOnDelete();
            $table->string('talep_turu', 40);
            $table->string('durum', 32)->default('bekliyor');
            $table->decimal('risk_tutari', 18, 2)->default(0);
            $table->string('para_birimi', 3)->default('TRY');
            $table->json('onceki_veri')->nullable();
            $table->json('istenen_veri')->nullable();
            $table->text('gerekce')->nullable();
            $table->foreignId('talep_eden_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('karar_veren_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('karar_notu')->nullable();
            $table->dateTime('karar_tarihi')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['firma_id', 'durum', 'created_at'], 'muh_alacak_onay_durum_idx');
            $table->index(['firma_id', 'alacak_plan_id', 'durum'], 'muh_alacak_onay_plan_idx');
            $table->index(['firma_id', 'talep_turu', 'durum'], 'muh_alacak_onay_tur_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('muhasebe_alacak_plan_onay_talepleri');
    }
};
