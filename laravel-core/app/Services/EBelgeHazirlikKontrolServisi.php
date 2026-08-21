<?php

namespace App\Services;

use App\Models\Muhasebe\Cari;

class EBelgeHazirlikKontrolServisi
{
    /**
     * @return array<int, string>
     */
    public function cariUyarilari(?Cari $cari): array
    {
        if (! $cari) {
            return [];
        }

        return $this->cariVerisindenUyarilar([
            'ad' => $cari->ad,
            'vergi_no' => $cari->vergi_no,
            'tc_no' => $cari->tc_no,
            'vergi_dairesi' => $cari->vergi_dairesi,
            'email' => $cari->email,
            'adres' => $cari->adres,
            'il' => $cari->il,
            'ilce' => $cari->ilce,
        ]);
    }

    /**
     * @param  array<string, mixed>  $veri
     * @return array<int, string>
     */
    public function cariVerisindenUyarilar(array $veri): array
    {
        $uyarilar = [];
        $vergiNo = $this->sadeceRakam($veri['vergi_no'] ?? '');
        $tcNo = $this->sadeceRakam($veri['tc_no'] ?? '');

        if ($this->bos($veri['ad'] ?? null)) {
            $uyarilar[] = 'Cari unvan/ad alanı e-belge için boş görünüyor.';
        }

        if ($vergiNo === '' && $tcNo === '') {
            $uyarilar[] = 'Vergi no veya T.C. kimlik no girilmemiş.';
        } elseif ($vergiNo !== '' && strlen($vergiNo) !== 10) {
            $uyarilar[] = 'Vergi no 10 haneli olmalı.';
        } elseif ($tcNo !== '' && strlen($tcNo) !== 11) {
            $uyarilar[] = 'T.C. kimlik no 11 haneli olmalı.';
        }

        if ($vergiNo !== '' && $this->bos($veri['vergi_dairesi'] ?? null)) {
            $uyarilar[] = 'Vergi no girildiği için vergi dairesi de doldurulmalı.';
        }

        if ($this->bos($veri['adres'] ?? null)) {
            $uyarilar[] = 'Cari adresi e-belge için eksik.';
        }

        if ($this->bos($veri['il'] ?? null)) {
            $uyarilar[] = 'İl alanı e-belge adresi için eksik.';
        }

        if ($this->bos($veri['ilce'] ?? null)) {
            $uyarilar[] = 'İlçe alanı e-belge adresi için eksik.';
        }

        if (! $this->bos($veri['email'] ?? null) && filter_var((string) $veri['email'], FILTER_VALIDATE_EMAIL) === false) {
            $uyarilar[] = 'E-posta biçimi geçerli görünmüyor.';
        }

        return $uyarilar;
    }

    /**
     * @param  array<string, mixed>  $ayarlar
     * @return array<int, string>
     */
    public function firmaUyarilari(array $ayarlar): array
    {
        $uyarilar = [];
        $vergiNo = $this->sadeceRakam($ayarlar['nette_fatura_gonderici_vergi_no'] ?? '');

        foreach ([
            'nette_fatura_gonderici_unvan' => 'Gönderici unvanı',
            'nette_fatura_gonderici_vergi_dairesi' => 'Gönderici vergi dairesi',
            'nette_fatura_gonderici_adres' => 'Gönderici adresi',
            'nette_fatura_gonderici_il' => 'Gönderici il',
            'nette_fatura_gonderici_ilce' => 'Gönderici ilçe',
        ] as $anahtar => $etiket) {
            if ($this->bos($ayarlar[$anahtar] ?? null)) {
                $uyarilar[] = $etiket.' eksik.';
            }
        }

        if ($vergiNo === '') {
            $uyarilar[] = 'Gönderici vergi no eksik.';
        } elseif (! in_array(strlen($vergiNo), [10, 11], true)) {
            $uyarilar[] = 'Gönderici vergi no/T.C. no 10 veya 11 haneli olmalı.';
        }

        return $uyarilar;
    }

    private function bos(mixed $deger): bool
    {
        return trim((string) $deger) === '';
    }

    private function sadeceRakam(mixed $deger): string
    {
        return preg_replace('/\D+/', '', (string) $deger) ?? '';
    }
}
