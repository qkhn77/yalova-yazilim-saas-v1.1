<?php

namespace App\Support\Barcode;

final class Ean13SvgUretici
{
    /** @var array<int, string> */
    private const L_KODLARI = [
        0 => '0001101',
        1 => '0011001',
        2 => '0010011',
        3 => '0111101',
        4 => '0100011',
        5 => '0110001',
        6 => '0101111',
        7 => '0111011',
        8 => '0110111',
        9 => '0001011',
    ];

    /** @var array<int, string> */
    private const G_KODLARI = [
        0 => '0100111',
        1 => '0110011',
        2 => '0011011',
        3 => '0100001',
        4 => '0011101',
        5 => '0111001',
        6 => '0000101',
        7 => '0010001',
        8 => '0001001',
        9 => '0010111',
    ];

    /** @var array<int, string> */
    private const R_KODLARI = [
        0 => '1110010',
        1 => '1100110',
        2 => '1101100',
        3 => '1000010',
        4 => '1011100',
        5 => '1001110',
        6 => '1010000',
        7 => '1000100',
        8 => '1001000',
        9 => '1110100',
    ];

    /** @var array<int, string> */
    private const PARITE = [
        0 => 'LLLLLL',
        1 => 'LLGLGG',
        2 => 'LLGGLG',
        3 => 'LLGGGL',
        4 => 'LGLLGG',
        5 => 'LGGLLG',
        6 => 'LGGGLL',
        7 => 'LGLGLG',
        8 => 'LGLGGL',
        9 => 'LGGLGL',
    ];

    public static function svgOlustur(string $hamDeger, int $genislik = 220, int $yukseklik = 70): ?string
    {
        $deger = preg_replace('/\D+/', '', trim($hamDeger)) ?? '';
        if ($deger === '') {
            return null;
        }

        if (strlen($deger) === 12) {
            $deger .= (string) static::kontrolHanesiHesapla($deger);
        }

        if (strlen($deger) !== 13) {
            return null;
        }

        if ((int) substr($deger, -1) !== static::kontrolHanesiHesapla(substr($deger, 0, 12))) {
            return null;
        }

        $desen = static::desenOlustur($deger);
        $modulSayisi = strlen($desen);
        $modulGenislik = max(1, (int) floor($genislik / $modulSayisi));
        $cizimGenisligi = $modulSayisi * $modulGenislik;
        $yaziYukseklik = 14;
        $barYukseklik = max(20, $yukseklik - $yaziYukseklik);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$cizimGenisligi.'" height="'.$yukseklik.'" viewBox="0 0 '.$cizimGenisligi.' '.$yukseklik.'" role="img" aria-label="EAN13 '.$deger.'">';
        $svg .= '<rect width="100%" height="100%" fill="#ffffff"/>';

        $x = 0;
        for ($i = 0; $i < $modulSayisi; $i++) {
            if ($desen[$i] === '1') {
                $svg .= '<rect x="'.$x.'" y="0" width="'.$modulGenislik.'" height="'.$barYukseklik.'" fill="#000000"/>';
            }
            $x += $modulGenislik;
        }

        $svg .= '<text x="'.(int) floor($cizimGenisligi / 2).'" y="'.($barYukseklik + 11).'" text-anchor="middle" font-size="11" font-family="Arial, sans-serif" fill="#111111">'.$deger.'</text>';
        $svg .= '</svg>';

        return $svg;
    }

    private static function kontrolHanesiHesapla(string $ilk12): int
    {
        $toplam = 0;
        for ($i = 0; $i < 12; $i++) {
            $hane = (int) $ilk12[$i];
            $toplam += ($i % 2 === 0) ? $hane : ($hane * 3);
        }

        return (10 - ($toplam % 10)) % 10;
    }

    private static function desenOlustur(string $deger): string
    {
        $ilk = (int) $deger[0];
        $pariteDeseni = static::PARITE[$ilk];
        $solAlt = substr($deger, 1, 6);
        $sagAlt = substr($deger, 7, 6);

        $desen = '101';
        for ($i = 0; $i < 6; $i++) {
            $hane = (int) $solAlt[$i];
            $parite = $pariteDeseni[$i];
            $desen .= $parite === 'L' ? static::L_KODLARI[$hane] : static::G_KODLARI[$hane];
        }

        $desen .= '01010';

        for ($i = 0; $i < 6; $i++) {
            $hane = (int) $sagAlt[$i];
            $desen .= static::R_KODLARI[$hane];
        }

        return $desen.'101';
    }
}

