<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('muhasebe_alacak_takip_notlari', function (Blueprint $table): void {
            $table->dateTime('odeme_sozu_tarihi')->nullable()->after('sonraki_takip_tarihi');
            $table->decimal('odeme_sozu_tutari', 18, 2)->nullable()->after('odeme_sozu_tarihi');
            $table->string('odeme_sozu_durumu', 32)->nullable()->after('odeme_sozu_tutari');
            $table->dateTime('kapanis_tarihi')->nullable()->after('odeme_sozu_durumu');
            $table->text('sonuc_notu')->nullable()->after('not');

            $table->index(['firma_id', 'odeme_sozu_durumu', 'odeme_sozu_tarihi'], 'muh_alacak_takip_soz_idx');
        });

        Schema::create('muhasebe_alacak_plan_revizyonlari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('alacak_plan_id')->constrained('muhasebe_alacak_planlari')->cascadeOnDelete();
            $table->string('revizyon_turu', 40);
            $table->json('onceki_veri')->nullable();
            $table->json('sonraki_veri')->nullable();
            $table->text('aciklama')->nullable();
            $table->foreignId('olusturan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['firma_id', 'alacak_plan_id'], 'muh_alacak_revizyon_plan_idx');
            $table->index(['firma_id', 'revizyon_turu', 'created_at'], 'muh_alacak_revizyon_tur_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('muhasebe_alacak_plan_revizyonlari');

        Schema::table('muhasebe_alacak_takip_notlari', function (Blueprint $table): void {
            $table->dropIndex('muh_alacak_takip_soz_idx');
            $table->dropColumn([
                'odeme_sozu_tarihi',
                'odeme_sozu_tutari',
                'odeme_sozu_durumu',
                'kapanis_tarihi',
                'sonuc_notu',
            ]);
        });
    }
};
