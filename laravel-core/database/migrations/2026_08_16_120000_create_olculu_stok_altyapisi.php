<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('muhasebe_birimler', function (Blueprint $table): void {
            if (! Schema::hasColumn('muhasebe_birimler', 'gib_birim_kodu')) {
                $table->string('gib_birim_kodu', 16)->nullable()->after('ad');
            }
        });

        Schema::table('stok_kartlari', function (Blueprint $table): void {
            $table->string('olculu_takip_turu', 24)->default('standart')->after('stok_takip');
            $table->string('olcu_yapisi', 16)->nullable()->after('olculu_takip_turu');
            $table->foreignId('ana_birim_id')->nullable()->constrained('muhasebe_birimler')->nullOnDelete();
            $table->foreignId('ikincil_birim_id')->nullable()->constrained('muhasebe_birimler')->nullOnDelete();
            $table->foreignId('varsayilan_islem_birimi_id')->nullable()->constrained('muhasebe_birimler')->nullOnDelete();
            $table->foreignId('varsayilan_fiyat_birimi_id')->nullable()->constrained('muhasebe_birimler')->nullOnDelete();
            $table->boolean('parcali_kullanima_izin')->default(false);
            $table->string('agirlik_turu', 16)->nullable();
            $table->index(['firma_id', 'olculu_takip_turu']);
        });

        Schema::create('stok_olculeri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('stok_id')->constrained('stok_kartlari')->restrictOnDelete();
            $table->string('kod', 64);
            $table->string('ad', 191);
            $table->string('takip_turu', 24);
            $table->string('olcu_birimi', 8)->nullable();
            $table->decimal('en', 20, 8)->nullable();
            $table->decimal('boy', 20, 8)->nullable();
            $table->decimal('yukseklik', 20, 8)->nullable();
            $table->decimal('bir_adet_agirlik', 20, 8)->nullable();
            $table->decimal('en_m', 20, 8)->nullable();
            $table->decimal('boy_m', 20, 8)->nullable();
            $table->decimal('yukseklik_m', 20, 8)->nullable();
            $table->decimal('bir_adet_ana_miktar', 20, 8)->nullable();
            $table->string('agirlik_turu', 16)->nullable();
            $table->boolean('aktif_mi')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['firma_id', 'stok_id', 'kod']);
            $table->index(['firma_id', 'stok_id', 'aktif_mi']);
        });

        /* Schema::create('stok_olcu_bakiyeleri', function (Blueprint $table): void {
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
        */

        /* Schema::create('stok_hareketi_olcu_dagilimlari', function (Blueprint $table): void {
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
        */
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_olculeri');
        Schema::table('stok_kartlari', function (Blueprint $table): void {
            foreach (['ana_birim_id', 'ikincil_birim_id', 'varsayilan_islem_birimi_id', 'varsayilan_fiyat_birimi_id'] as $kolon) {
                $table->dropConstrainedForeignId($kolon);
            }
            $table->dropIndex(['firma_id', 'olculu_takip_turu']);
            $table->dropColumn(['olculu_takip_turu', 'olcu_yapisi', 'parcali_kullanima_izin', 'agirlik_turu']);
        });
        Schema::table('muhasebe_birimler', fn (Blueprint $table) => $table->dropColumn('gib_birim_kodu'));
    }
};
