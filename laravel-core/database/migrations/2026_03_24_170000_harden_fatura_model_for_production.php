<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faturalar', function (Blueprint $table) {
            $table->string('belge_no', 64)->nullable()->after('fatura_no');
            $table->string('seri', 32)->nullable()->after('belge_no');
            $table->unsignedInteger('sira_no')->nullable()->after('seri');
            $table->decimal('doviz_kuru', 18, 8)->default(1)->after('para_birimi');
            $table->decimal('toplam_indirim', 18, 2)->default(0)->after('ara_toplam');
            $table->decimal('odenecek_tutar', 18, 2)->default(0)->after('genel_toplam');
            $table->decimal('odendi_tutari', 18, 2)->default(0)->after('odenecek_tutar');
            $table->decimal('acik_tutar', 18, 2)->default(0)->after('odendi_tutari');
            $table->string('odeme_durumu', 32)->default('odenmedi')->after('durum');
            $table->text('notlar')->nullable()->after('aciklama');
            $table->text('iptal_nedeni')->nullable()->after('notlar');
            $table->dateTime('iptal_edildi_at')->nullable()->after('iptal_nedeni');
            $table->unsignedBigInteger('iptal_eden_kullanici_id')->nullable()->after('iptal_edildi_at');
            $table->string('kaynak_tipi', 64)->nullable()->after('iptal_eden_kullanici_id');

            $table->index(['firma_id', 'cari_id']);
            $table->index(['firma_id', 'fatura_no']);
            $table->index(['firma_id', 'vade_tarihi']);
            $table->index(['firma_id', 'odeme_durumu']);
            $table->index(['firma_id', 'sira_no']);
        });

        DB::table('faturalar')->update([
            'toplam_indirim' => DB::raw('genel_indirim_tutari'),
            'odenecek_tutar' => DB::raw('genel_toplam'),
            'acik_tutar' => DB::raw('genel_toplam'),
        ]);

        Schema::table('fatura_kalemleri', function (Blueprint $table) {
            $table->foreignId('firma_id')->nullable()->after('id')->constrained('firmalar')->nullOnDelete();
            $table->unsignedInteger('satir_no')->default(1)->after('fatura_id');
            $table->string('kalem_tipi', 32)->default('stok_kalemi')->after('satir_no');
            $table->string('birim', 32)->default('AD')->after('miktar');
            $table->decimal('indirim_orani', 5, 2)->default(0)->after('birim_fiyat');
            $table->decimal('indirim_tutari', 18, 2)->default(0)->after('indirim_orani');
            $table->decimal('satir_toplami', 18, 2)->default(0)->after('kdv_tutari');
            $table->decimal('satir_genel_toplam', 18, 2)->default(0)->after('satir_toplami');
            $table->char('para_birimi', 3)->default('TRY')->after('satir_genel_toplam');

            $table->index(['firma_id', 'fatura_id']);
            $table->index(['firma_id', 'stok_id']);
        });

        $kalemler = DB::table('fatura_kalemleri')
            ->select(['id', 'fatura_id', 'hizmet_mi', 'satir_indirim_tutari', 'net_tutar', 'toplam'])
            ->orderBy('id')
            ->get();
        $faturaMap = DB::table('faturalar')->pluck('firma_id', 'id');
        $paraMap = DB::table('faturalar')->pluck('para_birimi', 'id');
        foreach ($kalemler as $kalem) {
            DB::table('fatura_kalemleri')->where('id', $kalem->id)->update([
                'firma_id' => (int) ($faturaMap[$kalem->fatura_id] ?? 0),
                'satir_no' => (int) $kalem->id,
                'kalem_tipi' => (bool) $kalem->hizmet_mi ? 'hizmet_kalemi' : 'stok_kalemi',
                'indirim_tutari' => $kalem->satir_indirim_tutari,
                'satir_toplami' => $kalem->net_tutar,
                'satir_genel_toplam' => $kalem->toplam,
                'para_birimi' => (string) ($paraMap[$kalem->fatura_id] ?? 'TRY'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('fatura_kalemleri', function (Blueprint $table) {
            $table->dropIndex('fatura_kalemleri_firma_id_fatura_id_index');
            $table->dropIndex('fatura_kalemleri_firma_id_stok_id_index');
            $table->dropConstrainedForeignId('firma_id');
            $table->dropColumn([
                'satir_no',
                'kalem_tipi',
                'birim',
                'indirim_orani',
                'indirim_tutari',
                'satir_toplami',
                'satir_genel_toplam',
                'para_birimi',
            ]);
        });

        Schema::table('faturalar', function (Blueprint $table) {
            $table->dropIndex('faturalar_firma_id_cari_id_index');
            $table->dropIndex('faturalar_firma_id_fatura_no_index');
            $table->dropIndex('faturalar_firma_id_vade_tarihi_index');
            $table->dropIndex('faturalar_firma_id_odeme_durumu_index');
            $table->dropIndex('faturalar_firma_id_sira_no_index');
            $table->dropColumn([
                'belge_no',
                'seri',
                'sira_no',
                'doviz_kuru',
                'toplam_indirim',
                'odenecek_tutar',
                'odendi_tutari',
                'acik_tutar',
                'odeme_durumu',
                'notlar',
                'iptal_nedeni',
                'iptal_edildi_at',
                'iptal_eden_kullanici_id',
                'kaynak_tipi',
            ]);
        });
    }
};
