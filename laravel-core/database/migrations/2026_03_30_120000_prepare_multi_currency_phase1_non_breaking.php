<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateFinansHareketleri();
        $this->updateCariHareketleri();
        $this->updateFaturaFinansKapatmalari();
        $this->updateFaturalar();
        $this->updateFaturaKalemleri();

        $this->backfillFinansHareketleri();
        $this->backfillCariHareketleri();
        $this->backfillFaturaFinansKapatmalari();
        $this->backfillFaturalar();
        $this->backfillFaturaKalemleri();
    }

    public function down(): void
    {
        Schema::table('fatura_kalemleri', function (Blueprint $table): void {
            foreach ([
                'baz_para_birimi',
                'baz_birim_fiyat',
                'baz_indirim_tutari',
                'baz_net_tutar',
                'baz_kdv_tutari',
                'baz_satir_toplami',
                'baz_satir_genel_toplam',
            ] as $column) {
                if (Schema::hasColumn('fatura_kalemleri', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('faturalar', function (Blueprint $table): void {
            foreach ([
                'baz_para_birimi',
                'baz_ara_toplam',
                'baz_toplam_indirim',
                'baz_kdv_toplam',
                'baz_genel_toplam',
                'baz_odenecek_tutar',
                'baz_odendi_tutari',
                'baz_acik_tutar',
            ] as $column) {
                if (Schema::hasColumn('faturalar', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('fatura_finans_kapatmalari', function (Blueprint $table): void {
            foreach ([
                'kur',
                'baz_para_birimi',
                'baz_uygulanan_tutar',
            ] as $column) {
                if (Schema::hasColumn('fatura_finans_kapatmalari', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('cari_hareketleri', function (Blueprint $table): void {
            foreach ([
                'kur',
                'baz_para_birimi',
                'baz_borc',
                'baz_alacak',
            ] as $column) {
                if (Schema::hasColumn('cari_hareketleri', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('finans_hareketleri', function (Blueprint $table): void {
            foreach ([
                'kur',
                'baz_para_birimi',
                'baz_tutar',
            ] as $column) {
                if (Schema::hasColumn('finans_hareketleri', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function updateFinansHareketleri(): void
    {
        Schema::table('finans_hareketleri', function (Blueprint $table): void {
            if (! Schema::hasColumn('finans_hareketleri', 'kur')) {
                $table->decimal('kur', 18, 8)->nullable()->after('para_birimi');
            }
            if (! Schema::hasColumn('finans_hareketleri', 'baz_para_birimi')) {
                $table->char('baz_para_birimi', 3)->nullable()->after('kur');
            }
            if (! Schema::hasColumn('finans_hareketleri', 'baz_tutar')) {
                $table->decimal('baz_tutar', 18, 2)->nullable()->after('tutar');
            }
        });
    }

    private function updateCariHareketleri(): void
    {
        Schema::table('cari_hareketleri', function (Blueprint $table): void {
            if (! Schema::hasColumn('cari_hareketleri', 'kur')) {
                $table->decimal('kur', 18, 8)->nullable()->after('para_birimi');
            }
            if (! Schema::hasColumn('cari_hareketleri', 'baz_para_birimi')) {
                $table->char('baz_para_birimi', 3)->nullable()->after('kur');
            }
            if (! Schema::hasColumn('cari_hareketleri', 'baz_borc')) {
                $table->decimal('baz_borc', 18, 2)->nullable()->after('borc');
            }
            if (! Schema::hasColumn('cari_hareketleri', 'baz_alacak')) {
                $table->decimal('baz_alacak', 18, 2)->nullable()->after('alacak');
            }
        });
    }

    private function updateFaturaFinansKapatmalari(): void
    {
        Schema::table('fatura_finans_kapatmalari', function (Blueprint $table): void {
            if (! Schema::hasColumn('fatura_finans_kapatmalari', 'kur')) {
                $table->decimal('kur', 18, 8)->nullable()->after('para_birimi');
            }
            if (! Schema::hasColumn('fatura_finans_kapatmalari', 'baz_para_birimi')) {
                $table->char('baz_para_birimi', 3)->nullable()->after('kur');
            }
            if (! Schema::hasColumn('fatura_finans_kapatmalari', 'baz_uygulanan_tutar')) {
                $table->decimal('baz_uygulanan_tutar', 18, 2)->nullable()->after('uygulanan_tutar');
            }
        });
    }

    private function updateFaturalar(): void
    {
        Schema::table('faturalar', function (Blueprint $table): void {
            if (! Schema::hasColumn('faturalar', 'baz_para_birimi')) {
                $table->char('baz_para_birimi', 3)->nullable()->after('para_birimi');
            }
            if (! Schema::hasColumn('faturalar', 'baz_ara_toplam')) {
                $table->decimal('baz_ara_toplam', 18, 2)->nullable()->after('ara_toplam');
            }
            if (! Schema::hasColumn('faturalar', 'baz_toplam_indirim')) {
                $table->decimal('baz_toplam_indirim', 18, 2)->nullable()->after('toplam_indirim');
            }
            if (! Schema::hasColumn('faturalar', 'baz_kdv_toplam')) {
                $table->decimal('baz_kdv_toplam', 18, 2)->nullable()->after('kdv_toplam');
            }
            if (! Schema::hasColumn('faturalar', 'baz_genel_toplam')) {
                $table->decimal('baz_genel_toplam', 18, 2)->nullable()->after('genel_toplam');
            }
            if (! Schema::hasColumn('faturalar', 'baz_odenecek_tutar')) {
                $table->decimal('baz_odenecek_tutar', 18, 2)->nullable()->after('odenecek_tutar');
            }
            if (! Schema::hasColumn('faturalar', 'baz_odendi_tutari')) {
                $table->decimal('baz_odendi_tutari', 18, 2)->nullable()->after('odendi_tutari');
            }
            if (! Schema::hasColumn('faturalar', 'baz_acik_tutar')) {
                $table->decimal('baz_acik_tutar', 18, 2)->nullable()->after('acik_tutar');
            }
        });
    }

    private function updateFaturaKalemleri(): void
    {
        Schema::table('fatura_kalemleri', function (Blueprint $table): void {
            if (! Schema::hasColumn('fatura_kalemleri', 'baz_para_birimi')) {
                $table->char('baz_para_birimi', 3)->nullable()->after('para_birimi');
            }
            if (! Schema::hasColumn('fatura_kalemleri', 'baz_birim_fiyat')) {
                $table->decimal('baz_birim_fiyat', 18, 2)->nullable()->after('birim_fiyat');
            }
            if (! Schema::hasColumn('fatura_kalemleri', 'baz_indirim_tutari')) {
                $table->decimal('baz_indirim_tutari', 18, 2)->nullable()->after('indirim_tutari');
            }
            if (! Schema::hasColumn('fatura_kalemleri', 'baz_net_tutar')) {
                $table->decimal('baz_net_tutar', 18, 2)->nullable()->after('net_tutar');
            }
            if (! Schema::hasColumn('fatura_kalemleri', 'baz_kdv_tutari')) {
                $table->decimal('baz_kdv_tutari', 18, 2)->nullable()->after('kdv_tutari');
            }
            if (! Schema::hasColumn('fatura_kalemleri', 'baz_satir_toplami')) {
                $table->decimal('baz_satir_toplami', 18, 2)->nullable()->after('satir_toplami');
            }
            if (! Schema::hasColumn('fatura_kalemleri', 'baz_satir_genel_toplam')) {
                $table->decimal('baz_satir_genel_toplam', 18, 2)->nullable()->after('satir_genel_toplam');
            }
        });
    }

    private function backfillFinansHareketleri(): void
    {
        DB::table('finans_hareketleri')
            ->whereNull('baz_para_birimi')
            ->update([
                'kur' => DB::raw("COALESCE(kur, 1)"),
                'baz_para_birimi' => DB::raw('para_birimi'),
                'baz_tutar' => DB::raw('tutar'),
            ]);
    }

    private function backfillCariHareketleri(): void
    {
        DB::table('cari_hareketleri')
            ->whereNull('baz_para_birimi')
            ->update([
                'kur' => DB::raw("COALESCE(kur, 1)"),
                'baz_para_birimi' => DB::raw('para_birimi'),
                'baz_borc' => DB::raw('borc'),
                'baz_alacak' => DB::raw('alacak'),
            ]);
    }

    private function backfillFaturaFinansKapatmalari(): void
    {
        DB::table('fatura_finans_kapatmalari')
            ->whereNull('baz_para_birimi')
            ->update([
                'kur' => DB::raw("COALESCE(kur, 1)"),
                'baz_para_birimi' => DB::raw('para_birimi'),
                'baz_uygulanan_tutar' => DB::raw('uygulanan_tutar'),
            ]);
    }

    private function backfillFaturalar(): void
    {
        DB::table('faturalar')
            ->whereNull('baz_para_birimi')
            ->update([
                'baz_para_birimi' => DB::raw('para_birimi'),
                'baz_ara_toplam' => DB::raw('ara_toplam'),
                'baz_toplam_indirim' => DB::raw('toplam_indirim'),
                'baz_kdv_toplam' => DB::raw('kdv_toplam'),
                'baz_genel_toplam' => DB::raw('genel_toplam'),
                'baz_odenecek_tutar' => DB::raw('odenecek_tutar'),
                'baz_odendi_tutari' => DB::raw('odendi_tutari'),
                'baz_acik_tutar' => DB::raw('acik_tutar'),
            ]);
    }

    private function backfillFaturaKalemleri(): void
    {
        DB::table('fatura_kalemleri')
            ->whereNull('baz_para_birimi')
            ->update([
                'baz_para_birimi' => DB::raw('para_birimi'),
                'baz_birim_fiyat' => DB::raw('birim_fiyat'),
                'baz_indirim_tutari' => DB::raw('indirim_tutari'),
                'baz_net_tutar' => DB::raw('net_tutar'),
                'baz_kdv_tutari' => DB::raw('kdv_tutari'),
                'baz_satir_toplami' => DB::raw('satir_toplami'),
                'baz_satir_genel_toplam' => DB::raw('satir_genel_toplam'),
            ]);
    }
};
