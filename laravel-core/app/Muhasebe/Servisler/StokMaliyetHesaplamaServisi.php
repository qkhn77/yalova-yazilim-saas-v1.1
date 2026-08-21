<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\StokHareketi;
use App\Models\Muhasebe\StokKarti;
use App\Muhasebe\Enumlar\StokHareketDurumu;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;

class StokMaliyetHesaplamaServisi
{
    private const PARA_BASAMAK = 8;

    /**
     * Ağırlıklı ortalama maliyet.
     *
     * @param  array{stok_takip:bool,onceki_miktar:string,miktar:string,birim_maliyet:string,islem_turu:StokHareketIslemTuru,mevcut_ortalama:string,mevcut_stok_degeri:string,tarih:mixed}  $girdi
     * @return array{birim_maliyet:string,toplam_maliyet:string,yeni_ortalama:string,yeni_stok_degeri:string,son_giris_maliyeti:?string,son_hareket_tarihi:mixed}
     */
    public function hareketMaliyetiHesapla(array $girdi): array
    {
        $stokTakip = (bool) $girdi['stok_takip'];
        $miktar = (string) $girdi['miktar'];
        $ortalama = (string) $girdi['mevcut_ortalama'];
        $stokDegeri = (string) $girdi['mevcut_stok_degeri'];
        $birimMaliyet = (string) $girdi['birim_maliyet'];
        $islem = $girdi['islem_turu'];

        if (! $stokTakip) {
            return [
                'birim_maliyet' => '0',
                'toplam_maliyet' => '0',
                'yeni_ortalama' => $ortalama,
                'yeni_stok_degeri' => $stokDegeri,
                'son_giris_maliyeti' => null,
                'son_hareket_tarihi' => $girdi['tarih'],
            ];
        }

        if ($this->isGiris($islem)) {
            $girisToplam = bcmul($miktar, $birimMaliyet, self::PARA_BASAMAK);
            $yeniDeger = bcadd($stokDegeri, $girisToplam, self::PARA_BASAMAK);
            $sonrakiMiktar = bcadd((string) $girdi['onceki_miktar'], $miktar, 8);
            $yeniOrtalama = bccomp($sonrakiMiktar, '0', 8) === 0 ? $ortalama : bcdiv($yeniDeger, $sonrakiMiktar, self::PARA_BASAMAK);

            return [
                'birim_maliyet' => $birimMaliyet,
                'toplam_maliyet' => $girisToplam,
                'yeni_ortalama' => $yeniOrtalama,
                'yeni_stok_degeri' => $yeniDeger,
                'son_giris_maliyeti' => $birimMaliyet,
                'son_hareket_tarihi' => $girdi['tarih'],
            ];
        }

        if ($this->isCikis($islem)) {
            $cikisBirimMaliyet = $ortalama;
            $cikisToplam = bcmul($miktar, $cikisBirimMaliyet, self::PARA_BASAMAK);
            $yeniDeger = bcsub($stokDegeri, $cikisToplam, self::PARA_BASAMAK);

            return [
                'birim_maliyet' => $cikisBirimMaliyet,
                'toplam_maliyet' => $cikisToplam,
                'yeni_ortalama' => $ortalama,
                'yeni_stok_degeri' => $yeniDeger,
                'son_giris_maliyeti' => null,
                'son_hareket_tarihi' => $girdi['tarih'],
            ];
        }

        return [
            'birim_maliyet' => $birimMaliyet,
            'toplam_maliyet' => bcmul($miktar, $birimMaliyet, self::PARA_BASAMAK),
            'yeni_ortalama' => $ortalama,
            'yeni_stok_degeri' => $stokDegeri,
            'son_giris_maliyeti' => null,
            'son_hareket_tarihi' => $girdi['tarih'],
        ];
    }

    public function stokDegeri(StokKarti $stok): string
    {
        return bcmul((string) ($stok->stok_miktari ?? 0), (string) ($stok->guncel_birim_maliyet ?? 0), self::PARA_BASAMAK);
    }

    /**
     * @return array{saglikli:bool,sorunlar:array<int,string>,hareket_sayisi:int}
     */
    public function stokZincirSaglikKontrolu(int $stokId): array
    {
        $sorunlar = [];
        $hareketler = StokHareketi::query()
            ->withoutGlobalScopes()
            ->where('stok_id', $stokId)
            ->where('durum', StokHareketDurumu::Aktif)
            ->orderBy('islem_tarihi')
            ->orderBy('id')
            ->get();
        $beklenenOnceki = null;
        $ortalama = '0.00000000';
        $stokDegeri = '0.00000000';

        foreach ($hareketler as $hareket) {
            $onceki = (string) $hareket->onceki_miktar;
            $sonraki = (string) $hareket->sonraki_miktar;
            $miktar = (string) $hareket->miktar;
            if ($beklenenOnceki !== null && bccomp($onceki, $beklenenOnceki, 8) !== 0) {
                $sorunlar[] = sprintf('Sıra kırığı: hareket #%d onceki_miktar beklenenle uyuşmuyor.', (int) $hareket->id);
            }

            $hesap = $this->hareketMaliyetiHesapla([
                'stok_takip' => true,
                'onceki_miktar' => $onceki,
                'miktar' => $miktar,
                'birim_maliyet' => (string) ($hareket->birim_maliyet ?? $hareket->birim_fiyat ?? 0),
                'islem_turu' => $hareket->islem_turu,
                'mevcut_ortalama' => $ortalama,
                'mevcut_stok_degeri' => $stokDegeri,
                'tarih' => $hareket->islem_tarihi,
            ]);

            if (bccomp((string) $hareket->toplam_maliyet, (string) $hesap['toplam_maliyet'], self::PARA_BASAMAK) !== 0) {
                $sorunlar[] = sprintf('Maliyet tutarsızlığı: hareket #%d toplam_maliyet beklenenle uyuşmuyor.', (int) $hareket->id);
            }
            if (bccomp($sonraki, '0', 8) < 0) {
                $sorunlar[] = sprintf('Negatif stok anomali: hareket #%d sonrası stok negatif.', (int) $hareket->id);
            }

            $beklenenOnceki = $sonraki;
            $ortalama = (string) $hesap['yeni_ortalama'];
            $stokDegeri = (string) $hesap['yeni_stok_degeri'];
        }

        return [
            'saglikli' => $sorunlar === [],
            'sorunlar' => $sorunlar,
            'hareket_sayisi' => $hareketler->count(),
        ];
    }

    private function isGiris(StokHareketIslemTuru $tur): bool
    {
        return in_array($tur, [
            StokHareketIslemTuru::Acilis,
            StokHareketIslemTuru::Alis,
            StokHareketIslemTuru::SatisIadesi,
            StokHareketIslemTuru::Iade,
            StokHareketIslemTuru::TransferGiris,
        ], true);
    }

    private function isCikis(StokHareketIslemTuru $tur): bool
    {
        return in_array($tur, [
            StokHareketIslemTuru::AcilisIptali,
            StokHareketIslemTuru::Satis,
            StokHareketIslemTuru::AlisIadesi,
            StokHareketIslemTuru::TransferCikis,
        ], true);
    }
}
