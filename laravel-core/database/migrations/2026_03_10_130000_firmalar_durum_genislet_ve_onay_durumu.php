<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * firmalar.durum: SaaS durum kümesi (beklemede, aktif, askida, suresi_doldu, iptal_edildi).
     * firma_kullanicilari.onay_durumu: giriş / panel ile uyumlu onay alanı.
     */
    public function up(): void
    {
        $surucu = Schema::getConnection()->getDriverName();
        $mysqlAilesi = in_array($surucu, ['mysql', 'mariadb'], true);

        if (Schema::hasTable('firmalar')) {
            // Önceki migrate denemesinde renameColumn hatası sonrası: yalnızca durum_yeni kalmış olabilir.
            if (Schema::hasColumn('firmalar', 'durum_yeni') && ! Schema::hasColumn('firmalar', 'durum')) {
                if ($mysqlAilesi) {
                    DB::statement("ALTER TABLE `firmalar` CHANGE `durum_yeni` `durum` VARCHAR(40) NOT NULL DEFAULT 'beklemede'");
                } else {
                    Schema::table('firmalar', function (Blueprint $table): void {
                        $table->renameColumn('durum_yeni', 'durum');
                    });
                }
                $this->firmaDurumIndeksiDene();
            } elseif (Schema::hasColumn('firmalar', 'durum') && ! Schema::hasColumn('firmalar', 'durum_yeni')) {
                $enumMu = false;
                if ($mysqlAilesi) {
                    $kolon = DB::selectOne("SHOW COLUMNS FROM firmalar WHERE Field = 'durum'");
                    $enumMu = $kolon && stripos((string) $kolon->Type, 'enum') !== false;
                }

                if ($enumMu) {
                    Schema::table('firmalar', function (Blueprint $table): void {
                        $table->string('durum_yeni', 40)->default('beklemede');
                    });

                    $harita = [
                        'taslak' => 'beklemede',
                        'aktif' => 'aktif',
                        'pasif' => 'askida',
                        'beklemede' => 'beklemede',
                        'askida' => 'askida',
                        'suresi_doldu' => 'suresi_doldu',
                        'iptal_edildi' => 'iptal_edildi',
                    ];

                    foreach (DB::table('firmalar')->cursor() as $satir) {
                        $eski = (string) $satir->durum;
                        $yeni = $harita[$eski] ?? 'beklemede';
                        DB::table('firmalar')->where('id', $satir->id)->update(['durum_yeni' => $yeni]);
                    }

                    Schema::table('firmalar', function (Blueprint $table): void {
                        $table->dropColumn('durum');
                    });

                    if ($mysqlAilesi) {
                        DB::statement("ALTER TABLE `firmalar` CHANGE `durum_yeni` `durum` VARCHAR(40) NOT NULL DEFAULT 'beklemede'");
                    } else {
                        Schema::table('firmalar', function (Blueprint $table): void {
                            $table->renameColumn('durum_yeni', 'durum');
                        });
                    }

                    $this->firmaDurumIndeksiDene();
                }
            }
        }

        if (Schema::hasTable('firma_kullanicilari') && ! Schema::hasColumn('firma_kullanicilari', 'onay_durumu')) {
            Schema::table('firma_kullanicilari', function (Blueprint $table): void {
                $table->string('onay_durumu', 40)->nullable()->default('aktif')->after('durum');
            });
        }
    }

    protected function firmaDurumIndeksiDene(): void
    {
        try {
            Schema::table('firmalar', function (Blueprint $table): void {
                $table->index('durum');
            });
        } catch (\Throwable) {
            // İndeks zaten varsa yut
        }
    }

    public function down(): void
    {
        // Geri alma: üretim verisi karmaşık olabileceği için boş bırakıldı.
    }
};
