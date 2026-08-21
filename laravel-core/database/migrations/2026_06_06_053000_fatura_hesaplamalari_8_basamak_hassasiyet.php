<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fatura hesapları içeride 8 basamak hassasiyetle tutulur; ekran gösterimi ayrı katmanda 2 basamak kalır.
     */
    public function up(): void
    {
        $this->faturalar(24, 8);
        $this->faturaKalemleri(24, 8);
        $this->stokHareketleri(24, 8);
        $this->stokKartlari(24, 8);
        $this->cariHareketleri(24, 8);
        $this->cariHareketEslesmeleri(24, 8);
        $this->faturaFinansKapatmalari(24, 8);
    }

    public function down(): void
    {
        $this->faturalar(18, 2);
        $this->faturaKalemleri(18, 2);
        $this->stokHareketleri(18, 2);
        $this->stokKartlari(18, 2);
        $this->cariHareketleri(18, 2);
        $this->cariHareketEslesmeleri(18, 2);
        $this->faturaFinansKapatmalari(18, 2);
    }

    private function faturalar(int $precision, int $scale): void
    {
        if (! Schema::hasTable('faturalar')) {
            return;
        }

        Schema::table('faturalar', function (Blueprint $table) use ($precision, $scale): void {
            foreach ([
                'ara_toplam',
                'toplam_indirim',
                'kdv_toplam',
                'genel_toplam',
                'odenecek_tutar',
                'odendi_tutari',
                'acik_tutar',
                'genel_indirim_tutari',
            ] as $kolon) {
                if (Schema::hasColumn('faturalar', $kolon)) {
                    $table->decimal($kolon, $precision, $scale)->default(0)->change();
                }
            }

            foreach ([
                'baz_ara_toplam',
                'baz_toplam_indirim',
                'baz_kdv_toplam',
                'baz_genel_toplam',
                'baz_odenecek_tutar',
                'baz_odendi_tutari',
                'baz_acik_tutar',
            ] as $kolon) {
                if (Schema::hasColumn('faturalar', $kolon)) {
                    $table->decimal($kolon, $precision, $scale)->nullable()->change();
                }
            }
        });
    }

    private function faturaKalemleri(int $precision, int $scale): void
    {
        if (! Schema::hasTable('fatura_kalemleri')) {
            return;
        }

        Schema::table('fatura_kalemleri', function (Blueprint $table) use ($precision, $scale): void {
            foreach ([
                'birim_fiyat',
                'satir_indirim_tutari',
                'indirim_tutari',
                'net_tutar',
                'kdv_tutari',
                'satir_toplami',
                'satir_genel_toplam',
                'toplam',
            ] as $kolon) {
                if (Schema::hasColumn('fatura_kalemleri', $kolon)) {
                    $table->decimal($kolon, $precision, $scale)->default(0)->change();
                }
            }

            foreach ([
                'baz_birim_fiyat',
                'baz_indirim_tutari',
                'baz_net_tutar',
                'baz_kdv_tutari',
                'baz_satir_toplami',
                'baz_satir_genel_toplam',
            ] as $kolon) {
                if (Schema::hasColumn('fatura_kalemleri', $kolon)) {
                    $table->decimal($kolon, $precision, $scale)->nullable()->change();
                }
            }
        });
    }

    private function stokHareketleri(int $precision, int $scale): void
    {
        if (! Schema::hasTable('stok_hareketleri')) {
            return;
        }

        Schema::table('stok_hareketleri', function (Blueprint $table) use ($precision, $scale): void {
            foreach ([
                'birim_fiyat',
                'birim_maliyet',
                'toplam',
                'toplam_maliyet',
            ] as $kolon) {
                if (Schema::hasColumn('stok_hareketleri', $kolon)) {
                    $table->decimal($kolon, $precision, $scale)->default(0)->change();
                }
            }
        });
    }

    private function cariHareketleri(int $precision, int $scale): void
    {
        if (! Schema::hasTable('cari_hareketleri')) {
            return;
        }

        Schema::table('cari_hareketleri', function (Blueprint $table) use ($precision, $scale): void {
            foreach ([
                'borc',
                'alacak',
            ] as $kolon) {
                if (Schema::hasColumn('cari_hareketleri', $kolon)) {
                    $table->decimal($kolon, $precision, $scale)->default(0)->change();
                }
            }

            foreach ([
                'baz_borc',
                'baz_alacak',
            ] as $kolon) {
                if (Schema::hasColumn('cari_hareketleri', $kolon)) {
                    $table->decimal($kolon, $precision, $scale)->nullable()->change();
                }
            }
        });
    }

    private function stokKartlari(int $precision, int $scale): void
    {
        if (! Schema::hasTable('stok_kartlari')) {
            return;
        }

        Schema::table('stok_kartlari', function (Blueprint $table) use ($precision, $scale): void {
            foreach ([
                'alis_fiyati',
                'satis_fiyati',
                'guncel_birim_maliyet',
                'stok_degeri',
            ] as $kolon) {
                if (Schema::hasColumn('stok_kartlari', $kolon)) {
                    $table->decimal($kolon, $precision, $scale)->default(0)->change();
                }
            }

            foreach ([
                'indirimli_fiyat',
                'son_giris_maliyeti',
            ] as $kolon) {
                if (Schema::hasColumn('stok_kartlari', $kolon)) {
                    $table->decimal($kolon, $precision, $scale)->nullable()->change();
                }
            }
        });
    }

    private function cariHareketEslesmeleri(int $precision, int $scale): void
    {
        if (! Schema::hasTable('cari_hareket_eslesmeleri')) {
            return;
        }

        Schema::table('cari_hareket_eslesmeleri', function (Blueprint $table) use ($precision, $scale): void {
            if (Schema::hasColumn('cari_hareket_eslesmeleri', 'eslesen_tutar')) {
                $table->decimal('eslesen_tutar', $precision, $scale)->default(0)->change();
            }
        });
    }

    private function faturaFinansKapatmalari(int $precision, int $scale): void
    {
        if (! Schema::hasTable('fatura_finans_kapatmalari')) {
            return;
        }

        Schema::table('fatura_finans_kapatmalari', function (Blueprint $table) use ($precision, $scale): void {
            if (Schema::hasColumn('fatura_finans_kapatmalari', 'uygulanan_tutar')) {
                $table->decimal('uygulanan_tutar', $precision, $scale)->default(0)->change();
            }

            if (Schema::hasColumn('fatura_finans_kapatmalari', 'baz_uygulanan_tutar')) {
                $table->decimal('baz_uygulanan_tutar', $precision, $scale)->nullable()->change();
            }
        });
    }
};
