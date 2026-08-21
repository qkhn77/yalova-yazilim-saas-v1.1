<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MySQL/MariaDB: information_schema; diğer sürücüler: SHOW INDEX yedek.
     */
    protected function indeksVarMi(string $tablo, string $indeksAdi): bool
    {
        $baglanti = Schema::getConnection();
        $surucu = $baglanti->getDriverName();
        $tabloTamAd = $baglanti->getTablePrefix().$tablo;

        if ($surucu === 'sqlite') {
            try {
                $satirlar = DB::select('PRAGMA index_list('.DB::getPdo()->quote($tabloTamAd).')');
                foreach ($satirlar as $satir) {
                    if (($satir->name ?? null) === $indeksAdi) {
                        return true;
                    }
                }
            } catch (Throwable) {
                return false;
            }

            return false;
        }

        if (in_array($surucu, ['mysql', 'mariadb'], true)) {
            $veritabani = $baglanti->getDatabaseName();
            $satir = DB::selectOne(
                'SELECT 1 AS x FROM information_schema.statistics
                 WHERE table_schema = ? AND table_name = ? AND index_name = ?
                 LIMIT 1',
                [$veritabani, $tabloTamAd, $indeksAdi]
            );

            return $satir !== null;
        }

        try {
            $satirlar = DB::select('SHOW INDEX FROM `'.$tabloTamAd.'`');
            foreach ($satirlar as $satir) {
                $anahtar = $satir->Key_name ?? $satir->key_name ?? null;
                if ($anahtar === $indeksAdi) {
                    return true;
                }
            }

            return false;
        } catch (Throwable) {
            return false;
        }
    }

    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (Schema::hasColumn('users', 'kullanici_adi') && ! $this->indeksVarMi('users', 'users_kullanici_adi_index')) {
                    $table->index('kullanici_adi', 'users_kullanici_adi_index');
                }

                if (Schema::hasColumn('users', 'email') && ! $this->indeksVarMi('users', 'users_email_index')) {
                    $table->index('email', 'users_email_index');
                }
            });
        }

        if (Schema::hasTable('firma_kullanicilari')) {
            Schema::table('firma_kullanicilari', function (Blueprint $table): void {
                if (! $this->indeksVarMi('firma_kullanicilari', 'fk_firma_durum_kullanici_idx')) {
                    $table->index(['firma_id', 'durum', 'kullanici_id'], 'fk_firma_durum_kullanici_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if ($this->indeksVarMi('users', 'users_kullanici_adi_index')) {
                    $table->dropIndex('users_kullanici_adi_index');
                }

                if ($this->indeksVarMi('users', 'users_email_index')) {
                    $table->dropIndex('users_email_index');
                }
            });
        }

        if (Schema::hasTable('firma_kullanicilari')) {
            Schema::table('firma_kullanicilari', function (Blueprint $table): void {
                if ($this->indeksVarMi('firma_kullanicilari', 'fk_firma_durum_kullanici_idx')) {
                    $table->dropIndex('fk_firma_durum_kullanici_idx');
                }
            });
        }
    }
};
