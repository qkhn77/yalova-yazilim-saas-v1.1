<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kasa_hareketleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('finans_hareket_id')->constrained('finans_hareketleri')->cascadeOnDelete();
            $table->foreignId('kasa_hesap_id')->constrained('kasa_hesaplari')->restrictOnDelete();
            $table->decimal('tutar', 18, 2);
            $table->char('para_birimi', 3)->default('TRY');
            $table->string('durum', 32)->default('aktif');
            $table->foreignId('iptal_edilen_hareket_id')->nullable()->constrained('kasa_hareketleri')->nullOnDelete();
            $table->timestamps();

            $table->index(['firma_id', 'kasa_hesap_id']);
            $table->index('finans_hareket_id');
            $table->index(['firma_id', 'durum']);
        });

        Schema::create('banka_hareketleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('finans_hareket_id')->constrained('finans_hareketleri')->cascadeOnDelete();
            $table->foreignId('banka_hesap_id')->constrained('banka_hesaplari')->restrictOnDelete();
            $table->decimal('tutar', 18, 2);
            $table->char('para_birimi', 3)->default('TRY');
            $table->string('durum', 32)->default('aktif');
            $table->foreignId('iptal_edilen_hareket_id')->nullable()->constrained('banka_hareketleri')->nullOnDelete();
            $table->timestamps();

            $table->index(['firma_id', 'banka_hesap_id']);
            $table->index('finans_hareket_id');
            $table->index(['firma_id', 'durum']);
        });

        Schema::create('pos_hareketleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('finans_hareket_id')->constrained('finans_hareketleri')->cascadeOnDelete();
            $table->foreignId('pos_hesap_id')->constrained('pos_hesaplari')->restrictOnDelete();
            $table->decimal('tutar', 18, 2);
            $table->char('para_birimi', 3)->default('TRY');
            $table->string('durum', 32)->default('aktif');
            $table->foreignId('iptal_edilen_hareket_id')->nullable()->constrained('pos_hareketleri')->nullOnDelete();
            $table->timestamps();

            $table->index(['firma_id', 'pos_hesap_id']);
            $table->index('finans_hareket_id');
            $table->index(['firma_id', 'durum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_hareketleri');
        Schema::dropIfExists('banka_hareketleri');
        Schema::dropIfExists('kasa_hareketleri');
    }
};
