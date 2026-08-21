<?php

namespace App\Muhasebe\Servisler;

use App\Muhasebe\Enumlar\OlculuStokTakipTuru;
use InvalidArgumentException;

class StokOlcuHesaplamaServisi
{
    public const KAYIT_OLCEGI = 8;
    public const HESAP_OLCEGI = 16;
    public const TOLERANS = '0.00000001';

    public function metreyeCevir(string $deger, string $birim): string
    {
        $deger = $this->pozitifDecimal($deger, 'Ölçü');

        return $this->kaydet(match (mb_strtolower(trim($birim), 'UTF-8')) {
            'mm' => bcdiv($deger, '1000', self::HESAP_OLCEGI),
            'cm' => bcdiv($deger, '100', self::HESAP_OLCEGI),
            'm', 'metre' => $deger,
            default => throw new InvalidArgumentException('Geçersiz ölçü birimi. Yalnız mm, cm veya metre kullanılabilir.'),
        });
    }

    public function kilogramaCevir(string $deger, string $birim): string
    {
        $deger = $this->pozitifDecimal($deger, 'Ağırlık');

        return $this->kaydet(match (mb_strtolower(trim($birim), 'UTF-8')) {
            'g', 'gram' => bcdiv($deger, '1000', self::HESAP_OLCEGI),
            'kg', 'kilogram' => $deger,
            't', 'ton' => bcmul($deger, '1000', self::HESAP_OLCEGI),
            default => throw new InvalidArgumentException('Geçersiz ağırlık birimi. Yalnız g, kg veya ton kullanılabilir.'),
        });
    }

    /** @param array{en?: string|null, boy?: string|null, yukseklik?: string|null, bir_adet_agirlik?: string|null} $olculer */
    public function birAdetAnaMiktar(OlculuStokTakipTuru|string $tur, array $olculer): string
    {
        $tur = $tur instanceof OlculuStokTakipTuru ? $tur : OlculuStokTakipTuru::from($tur);
        $gerekli = fn (string $alan): string => $this->pozitifDecimal((string) ($olculer[$alan] ?? ''), $this->alanAdi($alan));

        return $this->kaydet(match ($tur) {
            OlculuStokTakipTuru::Uzunluk => $gerekli('boy'),
            OlculuStokTakipTuru::Alan => bcmul($gerekli('en'), $gerekli('boy'), self::HESAP_OLCEGI),
            OlculuStokTakipTuru::Hacim => bcmul(bcmul($gerekli('en'), $gerekli('boy'), self::HESAP_OLCEGI), $gerekli('yukseklik'), self::HESAP_OLCEGI),
            OlculuStokTakipTuru::Agirlik => $gerekli('bir_adet_agirlik'),
            OlculuStokTakipTuru::Standart => throw new InvalidArgumentException('Standart stok için ölçü hesaplanamaz.'),
        });
    }

    public function adettenAnaMiktara(string $adet, string $birAdetAnaMiktar): string
    {
        return $this->kaydet(bcmul($this->negatifOlmayanDecimal($adet, 'Adet eşdeğeri'), $this->pozitifDecimal($birAdetAnaMiktar, 'Bir adet ana miktar'), self::HESAP_OLCEGI));
    }

    public function anaMiktardanAdede(string $anaMiktar, string $birAdetAnaMiktar): string
    {
        return $this->kaydet(bcdiv($this->negatifOlmayanDecimal($anaMiktar, 'Ana miktar'), $this->pozitifDecimal($birAdetAnaMiktar, 'Bir adet ana miktar'), self::HESAP_OLCEGI));
    }

    public function tutarliMi(string $anaMiktar, string $adet, string $birAdetAnaMiktar): bool
    {
        $beklenen = $this->adettenAnaMiktara($adet, $birAdetAnaMiktar);
        $fark = bcsub($this->kaydet($anaMiktar), $beklenen, self::KAYIT_OLCEGI);
        if (str_starts_with($fark, '-')) {
            $fark = substr($fark, 1);
        }

        // Adet eşdeğeri kayıt ölçeğinde (8 hane) yuvarlandığı için dönüşüm
        // katsayısı büyüdükçe hata payı da katsayıyla birlikte büyür. Örneğin
        // 2 m² / 6 = 0,33333333 adet ve geri çarpım 1,99999998 olur.
        $katsayi = $this->pozitifDecimal($birAdetAnaMiktar, 'Bir adet ana miktar');
        $tolerans = bccomp($katsayi, '1', self::HESAP_OLCEGI) > 0
            ? bcmul(self::TOLERANS, $katsayi, self::KAYIT_OLCEGI)
            : self::TOLERANS;

        return bccomp($fark, $tolerans, self::KAYIT_OLCEGI) <= 0;
    }

    public function tutarliligiDogrula(string $anaMiktar, string $adet, string $birAdetAnaMiktar): void
    {
        if (! $this->tutarliMi($anaMiktar, $adet, $birAdetAnaMiktar)) {
            throw new InvalidArgumentException('Ana miktar ile adet eşdeğeri birbiriyle uyumlu değil.');
        }
    }

    public function kaydet(string|int $deger): string
    {
        $deger = $this->decimal((string) $deger, 'Miktar');
        $isaret = str_starts_with($deger, '-') ? '-' : '';
        $mutlak = ltrim($deger, '+-');
        [$tam, $ondalik] = array_pad(explode('.', $mutlak, 2), 2, '');
        $ondalik = str_pad($ondalik, self::KAYIT_OLCEGI + 1, '0');
        $sonuc = $tam.'.'.substr($ondalik, 0, self::KAYIT_OLCEGI);
        if ((int) $ondalik[self::KAYIT_OLCEGI] >= 5) {
            $sonuc = bcadd($sonuc, '0.00000001', self::KAYIT_OLCEGI);
        }

        return ($isaret === '-' && bccomp($sonuc, '0', self::KAYIT_OLCEGI) !== 0 ? '-' : '').$sonuc;
    }

    private function pozitifDecimal(string $deger, string $alan): string
    {
        $deger = $this->decimal($deger, $alan);
        if (bccomp($deger, '0', self::HESAP_OLCEGI) <= 0) {
            throw new InvalidArgumentException($alan.' sıfırdan büyük olmalıdır.');
        }

        return $deger;
    }

    private function negatifOlmayanDecimal(string $deger, string $alan): string
    {
        $deger = $this->decimal($deger, $alan);
        if (bccomp($deger, '0', self::HESAP_OLCEGI) < 0) {
            throw new InvalidArgumentException($alan.' negatif olamaz.');
        }

        return $deger;
    }

    private function decimal(string $deger, string $alan): string
    {
        $deger = str_replace(',', '.', trim($deger));
        if (! preg_match('/^[+-]?\d+(?:\.\d+)?$/', $deger)) {
            throw new InvalidArgumentException($alan.' geçerli bir ondalık sayı olmalıdır.');
        }

        return $deger;
    }

    private function alanAdi(string $alan): string
    {
        return match ($alan) {
            'en' => 'En', 'boy' => 'Boy', 'yukseklik' => 'Yükseklik', default => 'Bir adet ağırlık',
        };
    }
}
