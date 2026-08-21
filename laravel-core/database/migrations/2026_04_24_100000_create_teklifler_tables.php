<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teklifler', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('cari_id')->nullable()->constrained('cariler')->nullOnDelete();
            $table->string('teklif_no', 64)->nullable();
            $table->string('durum', 32)->default('taslak');
            $table->string('baslik')->nullable();
            $table->dateTime('tarih');
            $table->date('gecerlilik_tarihi')->nullable();
            $table->foreignId('teklif_baski_sablonu_id')->nullable()->constrained('teklif_baski_sablonlari')->nullOnDelete();
            $table->char('para_birimi', 3)->default('TRY');
            $table->decimal('ara_toplam', 18, 2)->default(0);
            $table->decimal('toplam_indirim', 18, 2)->default(0);
            $table->decimal('kdv_toplam', 18, 2)->default(0);
            $table->decimal('genel_toplam', 18, 2)->default(0);
            $table->text('aciklama')->nullable();
            $table->text('notlar')->nullable();
            $table->text('kosullar')->nullable();
            $table->text('odeme_plani')->nullable();
            $table->string('teslim_suresi', 120)->nullable();
            $table->unsignedSmallInteger('revizyon_no')->default(1);
            $table->timestamp('gonderildi_at')->nullable();
            $table->timestamp('yanitlandi_at')->nullable();
            $table->foreignId('faturaya_donustu_fatura_id')->nullable()->constrained('faturalar')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['firma_id', 'durum']);
            $table->index(['firma_id', 'tarih']);
            $table->unique(['firma_id', 'teklif_no']);
        });

        Schema::create('teklif_kalemleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('teklif_id')->constrained('teklifler')->cascadeOnDelete();
            $table->foreignId('stok_id')->nullable()->constrained('stok_kartlari')->nullOnDelete();
            $table->string('kalem_tipi', 32)->default('stok_kalemi');
            $table->boolean('hizmet_mi')->default(false);
            $table->text('aciklama')->nullable();
            $table->string('birim', 32)->nullable();
            $table->decimal('miktar', 18, 4)->default(1);
            $table->decimal('birim_fiyat', 18, 2)->default(0);
            $table->decimal('indirim_orani', 5, 2)->default(0);
            $table->decimal('kdv_orani', 5, 2)->default(0);
            $table->decimal('net_tutar', 18, 2)->default(0);
            $table->decimal('kdv_tutari', 18, 2)->default(0);
            $table->decimal('toplam', 18, 2)->default(0);
            $table->char('para_birimi', 3)->default('TRY');
            $table->timestamps();

            $table->index(['teklif_id']);
            $table->index(['firma_id', 'stok_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teklif_kalemleri');
        Schema::dropIfExists('teklifler');
    }
};
