<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Teknik servis şema genişletmesi (additive). Eski 2026_03_27_* dosyalarına dokunulmaz.
 *
 * Taşınabilirlik: MySQL ve SQLite (PHPUnit sqlite) hedeflenir; sütun değişiklikleri için doctrine/dbal gereklidir (composer.json require).
 *
 * down(): firma_id tekrar NOT NULL yapılmadan önce NULL kayıt kontrolü yapılır; ihlalde exception (geri dönüşümsüz veri riski).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teknik_servis_tanim_cihazlar')) {
            return;
        }

        $this->tanimTablolariFirmaIdNullableYap();
        $this->arizaTablosuVarsayilanMiEkle();
        $this->teknikServisKayitlariGuncelle();
        $this->teknikServisKalemleriIskontoVeKdv();
        $this->hatirlatmaEkstraKolonlariKaldir();
        $this->childTablolaraEkIndeksler();
    }

    public function down(): void
    {
        if (! Schema::hasTable('teknik_servis_tanim_cihazlar')) {
            return;
        }

        $this->childTablolaraEkIndeksleriKaldir();
        $this->hatirlatmaEkstraKolonlariGeriGetir();
        $this->teknikServisKalemleriIskontoVeKdvGeriAl();
        $this->teknikServisKayitlariGeriAl();
        $this->arizaTablosuVarsayilanMiKaldir();
        $this->tanimTablolariFirmaIdNullableGeriAl();
    }

    private function tanimTablolariFirmaIdNullableYap(): void
    {
        $tablolar = [
            'teknik_servis_tanim_cihazlar',
            'teknik_servis_tanim_markalar',
            'teknik_servis_tanim_aksesuarlar',
            'teknik_servis_tanim_servis_durumlari',
        ];

        foreach ($tablolar as $tablo) {
            Schema::table($tablo, function (Blueprint $table) {
                $table->dropForeign(['firma_id']);
            });
            Schema::table($tablo, function (Blueprint $table) {
                $table->unsignedBigInteger('firma_id')->nullable()->change();
            });
            Schema::table($tablo, function (Blueprint $table) {
                $table->foreign('firma_id')->references('id')->on('firmalar')->nullOnDelete();
            });
        }

        Schema::table('teknik_servis_tanim_arizalar', function (Blueprint $table) {
            $table->dropForeign(['firma_id']);
            $table->dropForeign(['cihaz_id']);
        });
        Schema::table('teknik_servis_tanim_arizalar', function (Blueprint $table) {
            $table->unsignedBigInteger('firma_id')->nullable()->change();
            $table->unsignedBigInteger('cihaz_id')->nullable()->change();
        });
        Schema::table('teknik_servis_tanim_arizalar', function (Blueprint $table) {
            $table->foreign('firma_id')->references('id')->on('firmalar')->nullOnDelete();
            $table->foreign('cihaz_id')->references('id')->on('teknik_servis_tanim_cihazlar')->nullOnDelete();
        });
    }

    private function arizaTablosuVarsayilanMiEkle(): void
    {
        if (! Schema::hasColumn('teknik_servis_tanim_arizalar', 'varsayilan_mi')) {
            Schema::table('teknik_servis_tanim_arizalar', function (Blueprint $table) {
                $table->boolean('varsayilan_mi')->default(false);
            });
        }
    }

    private function teknikServisKayitlariGuncelle(): void
    {
        if (! Schema::hasTable('teknik_servis_kayitlari')) {
            return;
        }

        if ($this->uniqueIndexExists('teknik_servis_kayitlari', 'teknik_servis_kayitlari_fis_no_unique')) {
            Schema::table('teknik_servis_kayitlari', function (Blueprint $table) {
                $table->dropUnique('teknik_servis_kayitlari_fis_no_unique');
            });
        }

        if (! Schema::hasColumn('teknik_servis_kayitlari', 'musteri_ad_soyad')) {
            Schema::table('teknik_servis_kayitlari', function (Blueprint $table) {
                $table->string('musteri_ad_soyad', 191)->nullable();
                $table->string('musteri_tel', 64)->nullable();
                $table->unsignedInteger('km_bilgisi')->nullable();
            });
        }

        if (! $this->uniqueIndexExists('teknik_servis_kayitlari', 'ts_kayit_firma_fis_no_uq')) {
            Schema::table('teknik_servis_kayitlari', function (Blueprint $table) {
                $table->unique(['firma_id', 'fis_no'], 'ts_kayit_firma_fis_no_uq');
            });
        }

        $this->createIndexIfMissing('teknik_servis_kayitlari', ['firma_id', 'cari_id', 'kabul_tarihi'], 'ts_kayit_firma_cari_kabul_idx');
        $this->createIndexIfMissing('teknik_servis_kayitlari', ['firma_id', 'oncelik'], 'ts_kayit_firma_oncelik_idx');
        $this->createIndexIfMissing('teknik_servis_kayitlari', ['firma_id', 'servis_kanali'], 'ts_kayit_firma_kanal_idx');
    }

    private function teknikServisKalemleriIskontoVeKdv(): void
    {
        if (! Schema::hasTable('teknik_servis_kalemleri')) {
            return;
        }

        if (! Schema::hasColumn('teknik_servis_kalemleri', 'iskonto_tipi')) {
            Schema::table('teknik_servis_kalemleri', function (Blueprint $table) {
                $table->string('iskonto_tipi', 24)->nullable();
            });
        }

        Schema::table('teknik_servis_kalemleri', function (Blueprint $table) {
            $table->boolean('kdv_dahil_mi')->nullable()->change();
        });
    }

    private function hatirlatmaEkstraKolonlariKaldir(): void
    {
        if (! Schema::hasTable('teknik_servis_hatirlatmalari')) {
            return;
        }

        if (Schema::hasColumn('teknik_servis_hatirlatmalari', 'son_islenen_tarih')) {
            Schema::table('teknik_servis_hatirlatmalari', function (Blueprint $table) {
                $table->dropColumn(['son_islenen_tarih', 'tekrar_sayisi']);
            });
        }
    }

    private function childTablolaraEkIndeksler(): void
    {
        $ekler = [
            ['teknik_servis_durum_gecmisleri', 'ts_durum_gecm_firma_only', 'ts_durum_gecm_kayit_only'],
            ['teknik_servis_dokumanlari', 'ts_dokuman_firma_only', 'ts_dokuman_kayit_only'],
            ['teknik_servis_hatirlatmalari', 'ts_hatirlatma_firma_only', 'ts_hatirlatma_kayit_only'],
            ['teknik_servis_gorev_atamalari', 'ts_gorev_firma_only', 'ts_gorev_kayit_only'],
            ['teknik_servis_kalemleri', 'ts_kalem_firma_only', 'ts_kalem_kayit_only'],
            ['teknik_servis_islem_loglari', 'ts_islem_log_firma_only', 'ts_islem_log_kayit_only'],
            ['teknik_servis_muhasebe_baglantilari', 'ts_muhasebe_firma_only', 'ts_muhasebe_kayit_only'],
            ['teknik_servis_mesaj_loglari', 'ts_mesaj_log_firma_only', 'ts_mesaj_log_kayit_only'],
        ];

        foreach ($ekler as [$tablo, $firmaAd, $kayitAd]) {
            if (! Schema::hasTable($tablo)) {
                continue;
            }
            $this->createIndexIfMissing($tablo, 'firma_id', $firmaAd);
            $this->createIndexIfMissing($tablo, 'teknik_servis_kaydi_id', $kayitAd);
        }

        if (Schema::hasTable('teknik_servis_aksesuar_kayitlari')) {
            $this->createIndexIfMissing('teknik_servis_aksesuar_kayitlari', 'teknik_servis_kaydi_id', 'ts_aksesuar_kayit_kayit_only');
        }
    }

    /**
     * @param  array<int, string>|string  $columns
     */
    private function createIndexIfMissing(string $tablo, array|string $columns, string $indexAdi): void
    {
        if ($this->indexExists($tablo, $indexAdi)) {
            return;
        }
        Schema::table($tablo, function (Blueprint $table) use ($columns, $indexAdi) {
            $table->index($columns, $indexAdi);
        });
    }

    private function indexExists(string $tablo, string $indexAdi): bool
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            $satir = DB::selectOne(
                'SELECT COUNT(1) AS c FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
                [$tablo, $indexAdi]
            );

            return isset($satir->c) && (int) $satir->c > 0;
        }
        if ($driver === 'sqlite') {
            $satir = DB::selectOne('SELECT COUNT(1) AS c FROM sqlite_master WHERE type = ? AND tbl_name = ? AND name = ?', ['index', $tablo, $indexAdi]);

            return isset($satir->c) && (int) $satir->c > 0;
        }

        return false;
    }

    private function uniqueIndexExists(string $tablo, string $indexAdi): bool
    {
        return $this->indexExists($tablo, $indexAdi);
    }

    private function childTablolaraEkIndeksleriKaldir(): void
    {
        $kaldir = [
            ['teknik_servis_durum_gecmisleri', ['ts_durum_gecm_firma_only', 'ts_durum_gecm_kayit_only']],
            ['teknik_servis_dokumanlari', ['ts_dokuman_firma_only', 'ts_dokuman_kayit_only']],
            ['teknik_servis_hatirlatmalari', ['ts_hatirlatma_firma_only', 'ts_hatirlatma_kayit_only']],
            ['teknik_servis_gorev_atamalari', ['ts_gorev_firma_only', 'ts_gorev_kayit_only']],
            ['teknik_servis_aksesuar_kayitlari', ['ts_aksesuar_kayit_kayit_only']],
            ['teknik_servis_kalemleri', ['ts_kalem_firma_only', 'ts_kalem_kayit_only']],
            ['teknik_servis_islem_loglari', ['ts_islem_log_firma_only', 'ts_islem_log_kayit_only']],
            ['teknik_servis_muhasebe_baglantilari', ['ts_muhasebe_firma_only', 'ts_muhasebe_kayit_only']],
            ['teknik_servis_mesaj_loglari', ['ts_mesaj_log_firma_only', 'ts_mesaj_log_kayit_only']],
        ];

        foreach ($kaldir as [$tablo, $adlar]) {
            if (! Schema::hasTable($tablo)) {
                continue;
            }
            foreach ($adlar as $ad) {
                if (! $this->indexExists($tablo, $ad)) {
                    continue;
                }
                Schema::table($tablo, function (Blueprint $table) use ($ad) {
                    $table->dropIndex($ad);
                });
            }
        }
    }

    private function hatirlatmaEkstraKolonlariGeriGetir(): void
    {
        if (! Schema::hasTable('teknik_servis_hatirlatmalari')) {
            return;
        }
        if (! Schema::hasColumn('teknik_servis_hatirlatmalari', 'son_islenen_tarih')) {
            Schema::table('teknik_servis_hatirlatmalari', function (Blueprint $table) {
                $table->dateTime('son_islenen_tarih')->nullable();
                $table->unsignedInteger('tekrar_sayisi')->default(0);
            });
        }
    }

    private function teknikServisKalemleriIskontoVeKdvGeriAl(): void
    {
        if (! Schema::hasTable('teknik_servis_kalemleri')) {
            return;
        }
        if (Schema::hasColumn('teknik_servis_kalemleri', 'iskonto_tipi')) {
            Schema::table('teknik_servis_kalemleri', function (Blueprint $table) {
                $table->dropColumn('iskonto_tipi');
            });
        }
        Schema::table('teknik_servis_kalemleri', function (Blueprint $table) {
            $table->boolean('kdv_dahil_mi')->default(false)->nullable(false)->change();
        });
    }

    private function teknikServisKayitlariGeriAl(): void
    {
        if (! Schema::hasTable('teknik_servis_kayitlari')) {
            return;
        }

        foreach (['ts_kayit_firma_cari_kabul_idx', 'ts_kayit_firma_oncelik_idx', 'ts_kayit_firma_kanal_idx'] as $ad) {
            if ($this->indexExists('teknik_servis_kayitlari', $ad)) {
                Schema::table('teknik_servis_kayitlari', function (Blueprint $table) use ($ad) {
                    $table->dropIndex($ad);
                });
            }
        }

        if ($this->uniqueIndexExists('teknik_servis_kayitlari', 'ts_kayit_firma_fis_no_uq')) {
            Schema::table('teknik_servis_kayitlari', function (Blueprint $table) {
                $table->dropUnique('ts_kayit_firma_fis_no_uq');
            });
        }

        if (Schema::hasColumn('teknik_servis_kayitlari', 'musteri_ad_soyad')) {
            Schema::table('teknik_servis_kayitlari', function (Blueprint $table) {
                $table->dropColumn(['musteri_ad_soyad', 'musteri_tel', 'km_bilgisi']);
            });
        }

        $this->assertTeknikServisFisNoTekil();

        if (! $this->uniqueIndexExists('teknik_servis_kayitlari', 'teknik_servis_kayitlari_fis_no_unique')) {
            Schema::table('teknik_servis_kayitlari', function (Blueprint $table) {
                $table->unique('fis_no', 'teknik_servis_kayitlari_fis_no_unique');
            });
        }
    }

    private function arizaTablosuVarsayilanMiKaldir(): void
    {
        if (Schema::hasColumn('teknik_servis_tanim_arizalar', 'varsayilan_mi')) {
            Schema::table('teknik_servis_tanim_arizalar', function (Blueprint $table) {
                $table->dropColumn('varsayilan_mi');
            });
        }
    }

    /**
     * NULL firma/cihaz satırı varken NOT NULL geri alımı veri kaybı riski: exception ile durdurulur (geri dönüş kısmen irreversible).
     */
    private function tanimTablolariFirmaIdNullableGeriAl(): void
    {
        $this->assertFirmaIdNullYok('teknik_servis_tanim_cihazlar');
        $this->assertFirmaIdNullYok('teknik_servis_tanim_markalar');
        $this->assertFirmaIdNullYok('teknik_servis_tanim_aksesuarlar');
        $this->assertFirmaIdNullYok('teknik_servis_tanim_servis_durumlari');
        $this->assertFirmaIdNullYok('teknik_servis_tanim_arizalar');
        $this->assertCihazIdNullYok('teknik_servis_tanim_arizalar');

        Schema::table('teknik_servis_tanim_arizalar', function (Blueprint $table) {
            $table->dropForeign(['firma_id']);
            $table->dropForeign(['cihaz_id']);
        });
        Schema::table('teknik_servis_tanim_arizalar', function (Blueprint $table) {
            $table->unsignedBigInteger('firma_id')->nullable(false)->change();
            $table->unsignedBigInteger('cihaz_id')->nullable(false)->change();
        });
        Schema::table('teknik_servis_tanim_arizalar', function (Blueprint $table) {
            $table->foreign('firma_id')->references('id')->on('firmalar')->restrictOnDelete();
            $table->foreign('cihaz_id')->references('id')->on('teknik_servis_tanim_cihazlar')->restrictOnDelete();
        });

        $tablolar = [
            'teknik_servis_tanim_cihazlar',
            'teknik_servis_tanim_markalar',
            'teknik_servis_tanim_aksesuarlar',
            'teknik_servis_tanim_servis_durumlari',
        ];

        foreach ($tablolar as $tablo) {
            Schema::table($tablo, function (Blueprint $table) {
                $table->dropForeign(['firma_id']);
            });
            Schema::table($tablo, function (Blueprint $table) {
                $table->unsignedBigInteger('firma_id')->nullable(false)->change();
            });
            Schema::table($tablo, function (Blueprint $table) {
                $table->foreign('firma_id')->references('id')->on('firmalar')->restrictOnDelete();
            });
        }
    }

    private function assertFirmaIdNullYok(string $tablo): void
    {
        if (! Schema::hasTable($tablo)) {
            return;
        }
        $adet = (int) DB::table($tablo)->whereNull('firma_id')->count();
        if ($adet > 0) {
            throw new RuntimeException(
                "Rollback durduruldu: `{$tablo}` tablosunda firma_id NULL olan {$adet} kayıt var. Önce bu kayıtları silin veya bir firmaya atayın; ardından migrate:rollback deneyin."
            );
        }
    }

    private function assertCihazIdNullYok(string $tablo): void
    {
        if (! Schema::hasTable($tablo) || ! Schema::hasColumn($tablo, 'cihaz_id')) {
            return;
        }
        $adet = (int) DB::table($tablo)->whereNull('cihaz_id')->count();
        if ($adet > 0) {
            throw new RuntimeException(
                "Rollback durduruldu: `{$tablo}` tablosunda cihaz_id NULL olan {$adet} kayıt var."
            );
        }
    }

    /**
     * Eski şemada fis_no tablo genelinde tekildi; farklı firmalarda aynı fis_no oluşmuşsa geri alınamaz.
     */
    private function assertTeknikServisFisNoTekil(): void
    {
        if (! Schema::hasTable('teknik_servis_kayitlari')) {
            return;
        }
        $cift = DB::table('teknik_servis_kayitlari')
            ->select('fis_no')
            ->groupBy('fis_no')
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->exists();
        if ($cift) {
            throw new RuntimeException(
                'Rollback durduruldu: `teknik_servis_kayitlari` içinde aynı fis_no birden fazla satırda kullanılıyor; eski unique(fis_no) geri yüklenemez.'
            );
        }
    }
};
