<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('muhasebe_barkodlu_satislar')) {
            Schema::create('muhasebe_barkodlu_satislar', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
                $table->string('satis_no', 64);
                $table->dateTime('satis_tarihi');
                $table->foreignId('cari_id')->nullable()->constrained('cariler')->nullOnDelete();
                $table->string('odeme_tipi', 32)->default('nakit');
                $table->char('para_birimi', 3)->default('TRY');
                $table->decimal('ara_toplam', 18, 2)->default(0);
                $table->decimal('iskonto_toplami', 18, 2)->default(0);
                $table->decimal('kdv_toplami', 18, 2)->default(0);
                $table->decimal('genel_toplam', 18, 2)->default(0);
                $table->text('not')->nullable();
                $table->foreignId('olusturan_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['firma_id', 'satis_no'], 'muhasebe_barkodlu_satislar_firma_satis_no_uq');
                $table->index(['firma_id', 'satis_tarihi'], 'muhasebe_barkodlu_satislar_firma_tarih_idx');
            });
        }

        if (! Schema::hasTable('muhasebe_barkodlu_satis_kalemleri')) {
            Schema::create('muhasebe_barkodlu_satis_kalemleri', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
                $table->foreignId('satis_id')->constrained('muhasebe_barkodlu_satislar')->cascadeOnDelete();
                $table->foreignId('stok_id')->constrained('stok_kartlari')->restrictOnDelete();
                $table->string('barkod', 128)->nullable();
                $table->string('stok_adi', 255);
                $table->string('birim', 32)->default('AD');
                $table->decimal('miktar', 14, 4)->default(1);
                $table->decimal('birim_fiyat', 18, 2)->default(0);
                $table->decimal('iskonto_tutari', 18, 2)->default(0);
                $table->decimal('kdv_orani', 5, 2)->default(0);
                $table->decimal('kdv_tutari', 18, 2)->default(0);
                $table->decimal('satir_toplami', 18, 2)->default(0);
                $table->timestamps();

                $table->index(['firma_id', 'satis_id'], 'muhasebe_barkodlu_satis_kalemleri_firma_satis_idx');
                $table->index(['firma_id', 'stok_id'], 'muhasebe_barkodlu_satis_kalemleri_firma_stok_idx');
            });
        }

        if (! Schema::hasTable('muhasebe_etiket_sablonlari')) {
            Schema::create('muhasebe_etiket_sablonlari', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
                $table->string('ad', 191);
                $table->string('kod', 64);
                $table->unsignedSmallInteger('genislik_mm')->default(40);
                $table->unsignedSmallInteger('yukseklik_mm')->default(30);
                $table->string('barkod_tipi', 32)->default('code128');
                $table->boolean('varsayilan_mi')->default(false);
                $table->boolean('aktif')->default(true);
                $table->timestamps();

                $table->unique(['firma_id', 'kod'], 'muhasebe_etiket_sablonlari_firma_kod_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('muhasebe_etiket_sablonlari');
        Schema::dropIfExists('muhasebe_barkodlu_satis_kalemleri');
        Schema::dropIfExists('muhasebe_barkodlu_satislar');
    }
};
