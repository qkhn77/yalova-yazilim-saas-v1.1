<?php

namespace App\Muhasebe\Tanimlar;

use App\Muhasebe\Servisler\BirimKodResolver;
use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\CariGrubu;
use App\Models\Muhasebe\MuhasebeMarka;
use App\Models\Muhasebe\MuhasebeMarkaUretici;
use App\Models\Muhasebe\MuhasebeOdemeYontemi;
use App\Models\Muhasebe\MuhasebeStokModeli;
use App\Models\Muhasebe\ParaBirimi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokKategorisi;
use App\Models\Muhasebe\VergiOrani;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tanım silinmeden önce iş kayıtlarında kullanılıp kullanılmadığını kontrol eder.
 */
final class TanimKullanimDenetleyicisi
{
    public const KULLANIMDA_MESAJI = 'Bu tanım aktif olarak kullanılmaktadır. Önce ilgili kayıtları güncelleyin.';

    public static function kullanimdaMi(Model $model): bool
    {
        return match (true) {
            $model instanceof ParaBirimi => self::paraBirimiKullanimdaMi($model),
            $model instanceof StokKategorisi => self::stokKategorisiKullanimdaMi($model),
            $model instanceof Birim => self::birimKullanimdaMi($model),
            $model instanceof VergiOrani => self::vergiOraniKullanimdaMi($model),
            $model instanceof CariGrubu => self::cariGrubuKullanimdaMi($model),
            $model instanceof MuhasebeMarka => self::markaKullanimdaMi($model),
            $model instanceof MuhasebeMarkaUretici => self::markaUreticiKullanimdaMi($model),
            $model instanceof MuhasebeStokModeli => self::stokModeliKullanimdaMi($model),
            $model instanceof MuhasebeOdemeYontemi => self::odemeYontemiKullanimdaMi($model),
            default => self::isKaydiBaglantisiOlmayanTanim($model),
        };
    }

    private static function isKaydiBaglantisiOlmayanTanim(Model $model): bool
    {
        // Tasarım, malzeme türü, logo türü, varyant: iş tablolarında FK yok (STEP 15.1.1).
        return false;
    }

    private static function paraBirimiKullanimdaMi(ParaBirimi $para): bool
    {
        $kod = strtoupper(trim((string) $para->kod));
        if ($kod === null) {
            return false;
        }

        $firmaId = $para->firma_id !== null ? (int) $para->firma_id : null;
        $global = (bool) $para->is_sabit || $firmaId === null;

        $tablolar = [
            ['tablo' => 'cariler', 'firmaKolon' => 'firma_id'],
            ['tablo' => 'faturalar', 'firmaKolon' => 'firma_id'],
            ['tablo' => 'fatura_kalemleri', 'firmaKolon' => null],
            ['tablo' => 'finans_hareketleri', 'firmaKolon' => 'firma_id'],
            ['tablo' => 'cari_hareketleri', 'firmaKolon' => 'firma_id'],
            ['tablo' => 'stok_kartlari', 'firmaKolon' => 'firma_id'],
            ['tablo' => 'kasa_hesaplari', 'firmaKolon' => 'firma_id'],
            ['tablo' => 'banka_hesaplari', 'firmaKolon' => 'firma_id'],
            ['tablo' => 'pos_hesaplari', 'firmaKolon' => 'firma_id'],
            ['tablo' => 'kasa_hareketleri', 'firmaKolon' => 'firma_id'],
            ['tablo' => 'banka_hareketleri', 'firmaKolon' => 'firma_id'],
            ['tablo' => 'pos_hareketleri', 'firmaKolon' => 'firma_id'],
            ['tablo' => 'fatura_finans_kapatmalari', 'firmaKolon' => 'firma_id'],
        ];

        foreach ($tablolar as $satir) {
            $tablo = $satir['tablo'];
            if (! Schema::hasTable($tablo) || ! Schema::hasColumn($tablo, 'para_birimi')) {
                continue;
            }

            $sorgu = DB::table($tablo)->where('para_birimi', $kod);
            if (! $global && $firmaId !== null && $satir['firmaKolon'] !== null && Schema::hasColumn($tablo, $satir['firmaKolon'])) {
                $sorgu->where($satir['firmaKolon'], $firmaId);
            }

            if ($tablo === 'fatura_kalemleri' && ! $global && $firmaId !== null && Schema::hasColumn('faturalar', 'firma_id')) {
                $sorgu->whereExists(function ($q) use ($firmaId): void {
                    $q->selectRaw('1')
                        ->from('faturalar')
                        ->whereColumn('faturalar.id', 'fatura_kalemleri.fatura_id')
                        ->where('faturalar.firma_id', $firmaId);
                });
            }

            if ($sorgu->exists()) {
                return true;
            }
        }

        return false;
    }

    private static function stokKategorisiKullanimdaMi(StokKategorisi $kategori): bool
    {
        if (! Schema::hasTable('stok_kartlari') || ! Schema::hasColumn('stok_kartlari', 'kategori_id')) {
            return false;
        }

        return StokKarti::query()->where('kategori_id', (int) $kategori->getKey())->exists();
    }

    private static function birimKullanimdaMi(Birim $birim): bool
    {
        $kod = BirimKodResolver::normalize($birim->kod);
        if ($kod === '') {
            return false;
        }

        $kodlar = BirimKodResolver::acceptedCodes($kod);
        $kodSql = count($kodlar) > 1 ? 'IN (?, ?)' : '= ?';

        $firmaId = $birim->firma_id !== null ? (int) $birim->firma_id : null;
        $global = (bool) $birim->is_sabit || $firmaId === null;

        if (Schema::hasTable('stok_kartlari') && Schema::hasColumn('stok_kartlari', 'birim')) {
            $s = StokKarti::query()->whereRaw('UPPER(TRIM(birim)) '.$kodSql, $kodlar);
            if (! $global && $firmaId !== null) {
                $s->where('firma_id', $firmaId);
            }
            if ($s->exists()) {
                return true;
            }
        }

        if (Schema::hasTable('fatura_kalemleri') && Schema::hasColumn('fatura_kalemleri', 'birim')) {
            $q = DB::table('fatura_kalemleri')->whereRaw('UPPER(TRIM(birim)) '.$kodSql, $kodlar);
            if (! $global && $firmaId !== null && Schema::hasColumn('faturalar', 'firma_id')) {
                $q->whereExists(function ($sub) use ($firmaId): void {
                    $sub->selectRaw('1')
                        ->from('faturalar')
                        ->whereColumn('faturalar.id', 'fatura_kalemleri.fatura_id')
                        ->where('faturalar.firma_id', $firmaId);
                });
            }
            if ($q->exists()) {
                return true;
            }
        }

        return false;
    }

    private static function vergiOraniKullanimdaMi(VergiOrani $vergi): bool
    {
        $oran = (float) $vergi->oran;
        $firmaId = $vergi->firma_id !== null ? (int) $vergi->firma_id : null;
        $global = (bool) $vergi->is_sabit || $firmaId === null;

        $kdvFarkSql = DB::getDriverName() === 'sqlite'
            ? 'ABS(CAST(kdv_orani AS REAL) - ?) < 0.0001'
            : 'ABS(CAST(kdv_orani AS DECIMAL(18,6)) - ?) < 0.0001';

        if (Schema::hasTable('stok_kartlari') && Schema::hasColumn('stok_kartlari', 'kdv_orani')) {
            $s = StokKarti::query()->whereRaw($kdvFarkSql, [$oran]);
            if (! $global && $firmaId !== null) {
                $s->where('firma_id', $firmaId);
            }
            if ($s->exists()) {
                return true;
            }
        }

        if (Schema::hasTable('fatura_kalemleri') && Schema::hasColumn('fatura_kalemleri', 'kdv_orani')) {
            $q = DB::table('fatura_kalemleri')->whereRaw($kdvFarkSql, [$oran]);
            if (! $global && $firmaId !== null && Schema::hasColumn('faturalar', 'firma_id')) {
                $q->whereExists(function ($sub) use ($firmaId): void {
                    $sub->selectRaw('1')
                        ->from('faturalar')
                        ->whereColumn('faturalar.id', 'fatura_kalemleri.fatura_id')
                        ->where('faturalar.firma_id', $firmaId);
                });
            }
            if ($q->exists()) {
                return true;
            }
        }

        return false;
    }

    private static function cariGrubuKullanimdaMi(CariGrubu $grup): bool
    {
        if (! Schema::hasTable('cariler') || ! Schema::hasColumn('cariler', 'cari_grubu_id')) {
            return false;
        }

        return DB::table('cariler')->where('cari_grubu_id', (int) $grup->getKey())->exists();
    }

    private static function markaKullanimdaMi(MuhasebeMarka $marka): bool
    {
        return MuhasebeStokModeli::query()->where('marka_id', (int) $marka->getKey())->exists();
    }

    private static function markaUreticiKullanimdaMi(MuhasebeMarkaUretici $markaUretici): bool
    {
        $ad = trim((string) $markaUretici->ad);
        if ($ad === '' || ! Schema::hasColumn('stok_kartlari', 'marka_uretici')) {
            return false;
        }

        $sorgu = StokKarti::query()->where('marka_uretici', $ad);
        if ($markaUretici->firma_id !== null && ! (bool) $markaUretici->is_sabit) {
            $sorgu->where('firma_id', (int) $markaUretici->firma_id);
        }

        return $sorgu->exists();
    }

    private static function stokModeliKullanimdaMi(MuhasebeStokModeli $model): bool
    {
        // Stok kartında model FK yok; alt kayıt yok sayılır.
        return false;
    }

    private static function odemeYontemiKullanimdaMi(MuhasebeOdemeYontemi $yontem): bool
    {
        // finans_hareketleri vb. şemada odeme_yontemi_id yok (STEP 15.1.1).
        unset($yontem);

        return false;
    }
}
