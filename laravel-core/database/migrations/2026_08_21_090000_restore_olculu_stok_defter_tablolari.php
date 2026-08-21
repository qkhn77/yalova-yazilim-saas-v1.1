<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ölçülü stok altyapısının ilk migration'ında bazı tablolar yorum satırına
     * alındığı için migration "çalıştı" görünmesine rağmen üretimde eksik
     * kalmıştı. Bu tablolar fiziksel parça takibi olmadan yalnızca stok, ölçü
     * ve depo düzeyinde tutulur.
     */
    public function up(): void
    {
        if (! Schema::hasTable('stok_olcu_bakiyeleri')) {
            Schema::create('stok_olcu_bakiyeleri', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
                $table->foreignId('stok_id')->constrained('stok_kartlari')->restrictOnDelete();
                $table->foreignId('stok_olcusu_id')->constrained('stok_olculeri')->restrictOnDelete();
                $table->foreignId('depo_id')->constrained('muhasebe_depolar')->restrictOnDelete();
                $table->decimal('ana_miktar', 20, 8)->default(0);
                $table->decimal('adet_esdegeri', 20, 8)->default(0);
                $table->decimal('rezerve_ana_miktar', 20, 8)->default(0);
                $table->decimal('rezerve_adet_esdegeri', 20, 8)->default(0);
                $table->decimal('donusum_ana_miktari', 20, 8)->nullable();
                $table->string('durum', 16)->default('aktif');
                $table->timestamps();
                $table->unique(['firma_id', 'stok_id', 'stok_olcusu_id', 'depo_id'], 'stok_olcu_bakiye_tekil');
                $table->index(['firma_id', 'stok_id', 'depo_id']);
            });
        }

        if (! Schema::hasTable('stok_hareketi_olcu_dagilimlari')) {
            Schema::create('stok_hareketi_olcu_dagilimlari', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
                $table->foreignId('stok_hareketi_id')->constrained('stok_hareketleri')->restrictOnDelete();
                $table->foreignId('stok_id')->constrained('stok_kartlari')->restrictOnDelete();
                $table->foreignId('stok_olcusu_id')->constrained('stok_olculeri')->restrictOnDelete();
                $table->foreignId('stok_olcu_bakiyesi_id')->constrained('stok_olcu_bakiyeleri')->restrictOnDelete();
                $table->foreignId('depo_id')->constrained('muhasebe_depolar')->restrictOnDelete();
                $table->decimal('ana_miktar', 20, 8);
                $table->decimal('adet_esdegeri', 20, 8);
                $table->foreignId('islem_birimi_id')->constrained('muhasebe_birimler')->restrictOnDelete();
                $table->decimal('girilen_miktar', 20, 8);
                $table->string('takip_turu', 24);
                $table->string('olcu_birimi', 8)->nullable();
                $table->decimal('en', 20, 8)->nullable();
                $table->decimal('boy', 20, 8)->nullable();
                $table->decimal('yukseklik', 20, 8)->nullable();
                $table->decimal('en_m', 20, 8)->nullable();
                $table->decimal('boy_m', 20, 8)->nullable();
                $table->decimal('yukseklik_m', 20, 8)->nullable();
                $table->decimal('bir_adet_ana_miktar', 20, 8);
                $table->timestamps();
                $table->index(['firma_id', 'stok_hareketi_id']);
                $table->index(['firma_id', 'stok_olcu_bakiyesi_id'], 'hareket_olcu_bakiye_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_hareketi_olcu_dagilimlari');
        Schema::dropIfExists('stok_olcu_bakiyeleri');
    }
};
