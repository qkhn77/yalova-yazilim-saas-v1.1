<?php

namespace App\Muhasebe\Servisler;

use App\Muhasebe\Exceptions\IsKuraliIstisnasi;

/** BCMath tabanlı ölçü fiyat dönüşümleri. */
class FaturaOlcuFiyatlandirmaServisi
{
    public const SCALE = 8;

    public function adetFiyati(string $anaBirimFiyati, string $birAdetAnaMiktar): string
    {
        $this->pozitif($birAdetAnaMiktar, 'Bir adet ana miktarı');

        return $this->normal(bcmul($anaBirimFiyati, $birAdetAnaMiktar, 16));
    }

    public function anaBirimFiyati(string $adetFiyati, string $birAdetAnaMiktar): string
    {
        $this->pozitif($birAdetAnaMiktar, 'Bir adet ana miktarı');

        return $this->normal(bcdiv($adetFiyati, $birAdetAnaMiktar, 16));
    }

    public function toplam(string $birimFiyat, string $fiyatMiktari): string
    {
        $this->pozitif($fiyatMiktari, 'Fiyat miktarı');

        return $this->normal(bcmul($birimFiyat, $fiyatMiktari, 16));
    }

    /** @param list<string> $katSayilari */
    public function cokluOlcuDonusumu(array $katSayilari, string $fiyat, string $fiyatBirimi = 'ana', bool $dogrudanOrtakAdetFiyati = false): array
    {
        if ($katSayilari === []) {
            throw new IsKuraliIstisnasi('Fiyat dönüşümü için en az bir ölçü seçilmelidir.');
        }
        $this->pozitif($fiyat, 'Birim fiyat');
        foreach ($katSayilari as $katsayi) {
            $this->pozitif((string) $katsayi, 'Ölçü katsayısı');
        }
        $ilk = (string) $katSayilari[0];
        $ayni = count(array_filter($katSayilari, fn ($katsayi): bool => bccomp((string) $katsayi, $ilk, 8) !== 0)) === 0;
        if ($fiyatBirimi === 'adet' && ! $ayni && ! $dogrudanOrtakAdetFiyati) {
            throw new IsKuraliIstisnasi('Farklı ölçü katsayılarında ortak adet fiyatı otomatik dönüştürülemez.');
        }

        return [
            'birim' => $fiyatBirimi,
            'ana_birim_fiyati' => $fiyatBirimi === 'adet' && $ayni ? $this->anaBirimFiyati($fiyat, $ilk) : $this->normal($fiyat),
            'adet_fiyati' => $fiyatBirimi === 'ana' && $ayni ? $this->adetFiyati($fiyat, $ilk) : $this->normal($fiyat),
            'kat_sayilari' => array_map(fn ($katsayi): string => $this->normal((string) $katsayi), $katSayilari),
            'donusum_turu' => $ayni ? 'ortak_katsayi' : ($dogrudanOrtakAdetFiyati ? 'dogrudan_ortak_adet' : 'ana_birim'),
        ];
    }

    private function pozitif(string $deger, string $alan): void
    {
        if (! is_numeric($deger) || bccomp($deger, '0', 16) <= 0) {
            throw new IsKuraliIstisnasi($alan.' sıfırdan büyük olmalıdır.');
        }
    }

    private function normal(string $deger): string
    {
        return bcadd($deger, '0', self::SCALE);
    }
}
