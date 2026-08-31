<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teknik_servis_kalemleri')) {
            return;
        }

        Schema::table('teknik_servis_kalemleri', function (Blueprint $table): void {
            $table->unsignedBigInteger('satir_no')->nullable()->after('teknik_servis_kaydi_id');
            $table->string('kalem_tipi', 32)->default('stok_kalemi')->after('satir_no');
            $table->unsignedBigInteger('depo_id')->nullable()->after('stok_id');
            $table->json('seri_nolari')->nullable()->after('depo_id');
            $table->date('garanti_baslangic_tarihi')->nullable()->after('seri_nolari');
            $table->date('garanti_bitis_tarihi')->nullable()->after('garanti_baslangic_tarihi');
            $table->unsignedBigInteger('fiyat_birimi_id')->nullable()->after('birim');
            $table->decimal('fiyat_miktari', 18, 8)->nullable()->after('miktar');
            $table->decimal('ana_miktar', 18, 8)->nullable()->after('fiyat_miktari');
            $table->decimal('adet_esdegeri', 18, 8)->nullable()->after('ana_miktar');
            $table->json('olcu_donusum_snapshot')->nullable()->after('adet_esdegeri');
            $table->string('olcu_satis_birimi', 64)->nullable()->after('olcu_donusum_snapshot');
            $table->boolean('dogrudan_ortak_adet_fiyati')->default(false)->after('olcu_satis_birimi');
            $table->decimal('indirim_orani', 5, 2)->default(0)->after('iskonto_tutari');
            $table->decimal('indirim_tutari', 18, 2)->default(0)->after('indirim_orani');
            $table->decimal('kdv_tutari', 18, 2)->default(0)->after('indirim_tutari');
            $table->decimal('toplam', 18, 2)->default(0)->after('satir_toplami');
            $table->decimal('net_tutar', 18, 2)->default(0)->after('toplam');
            $table->decimal('satir_genel_toplam', 18, 2)->default(0)->after('net_tutar');
            $table->decimal('satir_indirim_tutari', 18, 2)->default(0)->after('satir_genel_toplam');
        });

        Schema::table('teknik_servis_kalemleri', function (Blueprint $table): void {
            $table->index(['firma_id', 'depo_id', 'stok_id'], 'ts_kalem_firma_depo_stok_idx');
            $table->index(['firma_id', 'satir_no'], 'ts_kalem_firma_satir_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('teknik_servis_kalemleri')) {
            return;
        }

        Schema::table('teknik_servis_kalemleri', function (Blueprint $table): void {
            $table->dropIndex('ts_kalem_firma_depo_stok_idx');
            $table->dropIndex('ts_kalem_firma_satir_idx');
            $table->dropColumn([
                'satir_no', 'kalem_tipi', 'depo_id', 'seri_nolari',
                'garanti_baslangic_tarihi', 'garanti_bitis_tarihi', 'fiyat_birimi_id',
                'fiyat_miktari', 'ana_miktar', 'adet_esdegeri', 'olcu_donusum_snapshot',
                'olcu_satis_birimi', 'dogrudan_ortak_adet_fiyati', 'indirim_orani',
                'indirim_tutari', 'kdv_tutari', 'toplam', 'net_tutar',
                'satir_genel_toplam', 'satir_indirim_tutari',
            ]);
        });
    }
};
