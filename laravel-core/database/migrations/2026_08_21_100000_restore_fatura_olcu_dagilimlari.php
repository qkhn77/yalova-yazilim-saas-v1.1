<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fatura_kalemi_olcu_dagilimlari')) {
            return;
        }

        Schema::create('fatura_kalemi_olcu_dagilimlari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->restrictOnDelete();
            $table->foreignId('fatura_kalemi_id')->constrained('fatura_kalemleri')->cascadeOnDelete();
            $table->unsignedBigInteger('kaynak_olcu_dagilimi_id')->nullable();
            $table->foreignId('stok_id')->constrained('stok_kartlari')->restrictOnDelete();
            $table->foreignId('stok_olcusu_id')->constrained('stok_olculeri')->restrictOnDelete();
            $table->foreignId('stok_olcu_bakiyesi_id')->nullable()->constrained('stok_olcu_bakiyeleri')->restrictOnDelete();
            $table->foreignId('depo_id')->constrained('muhasebe_depolar')->restrictOnDelete();
            $table->foreignId('islem_birimi_id')->nullable()->constrained('muhasebe_birimler')->nullOnDelete();
            $table->decimal('girilen_miktar', 20, 8);
            $table->decimal('ana_miktar', 20, 8);
            $table->decimal('adet_esdegeri', 20, 8);
            $table->unsignedInteger('sira')->default(0);
            $table->string('takip_turu', 32);
            $table->string('olcu_birimi', 16)->nullable();
            $table->decimal('en', 20, 8)->nullable();
            $table->decimal('boy', 20, 8)->nullable();
            $table->decimal('yukseklik', 20, 8)->nullable();
            $table->decimal('en_m', 20, 8)->nullable();
            $table->decimal('boy_m', 20, 8)->nullable();
            $table->decimal('yukseklik_m', 20, 8)->nullable();
            $table->decimal('bir_adet_ana_miktar', 20, 8)->nullable();
            $table->timestamps();
            $table->index(['firma_id', 'fatura_kalemi_id'], 'fatura_olcu_dagilim_kalem_idx');
            $table->index(['firma_id', 'stok_id', 'depo_id'], 'fatura_olcu_dagilim_stok_idx');
            $table->index('kaynak_olcu_dagilimi_id', 'fk_olcu_kaynak_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fatura_kalemi_olcu_dagilimlari');
    }
};
