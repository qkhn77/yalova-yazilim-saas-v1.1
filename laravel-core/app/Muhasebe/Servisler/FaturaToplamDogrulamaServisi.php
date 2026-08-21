<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\Fatura;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;

/**
 * Başlık ara_toplam / kdv_toplam / genel_toplam ile kalem satırlarının tutarlılığını doğrular.
 *
 * Varsayımlar:
 * - ara_toplam: KDV matrahı (satır netlerinin toplamı)
 * - kdv_toplam: satır KDV tutarlarının toplamı
 * - genel_toplam: ara_toplam + kdv_toplam - genel_indirim_tutari
 * - Birim fiyat × miktar işlemleri içeride 8 ondalık hassasiyetle kapatılır.
 * - Ekran/e-belge gösterimi gereken yerlerde 2 basamağa ayrıca formatlanır.
 */
class FaturaToplamDogrulamaServisi
{
    private const HESAP_BASAMAK = 8;

    private const ARA_HESAP_BASAMAK = 12;

    public function dogrula(Fatura $fatura): void
    {
        if ($fatura->tur === FaturaTuru::Proforma) {
            return;
        }

        $kalemler = $fatura->onayKalemleri()->get();
        if ($kalemler->isEmpty()) {
            throw new IsKuraliIstisnasi('Faturayı onaylayabilmek için en az bir satır (kalem) eklemeniz gerekir.');
        }

        $kdvDahil = (bool) $fatura->kdv_dahil_fiyatlandirma_mi;

        $toplamNet = '0.00000000';
        $toplamKdv = '0.00000000';

        foreach ($kalemler as $kalem) {
            $miktar = (string) $kalem->miktar;
            $birim = (string) $kalem->birim_fiyat;
            $satirInd = (string) $kalem->satir_indirim_tutari;
            $kdvOran = (string) $kalem->kdv_orani;

            $ham = $this->yuvarla8(bcmul($miktar, $birim, self::ARA_HESAP_BASAMAK));
            $netSatir = $this->yuvarla8(bcsub($ham, $satirInd, self::ARA_HESAP_BASAMAK));

            if (bccomp($netSatir, '0', self::HESAP_BASAMAK) === -1) {
                throw new IsKuraliIstisnasi(
                    'Satır tutarı (indirim sonrası) sıfırdan küçük olamaz. Lütfen miktar, birim fiyat ve satır indirimini kontrol edin (satır #'.$kalem->getKey().').'
                );
            }

            if ($kdvDahil) {
                $oran = bcdiv($kdvOran, '100', self::ARA_HESAP_BASAMAK);
                $bolen = bcadd('1', $oran, self::ARA_HESAP_BASAMAK);
                $netMatrah = $this->yuvarla8(bcdiv($netSatir, $bolen, self::ARA_HESAP_BASAMAK));
                $kdvSatir = $this->yuvarla8(bcsub($netSatir, $netMatrah, self::ARA_HESAP_BASAMAK));
                $satirGenel = $netSatir;
                $netForAra = $netMatrah;
                $kdvForTop = $kdvSatir;
            } else {
                $kdvSatir = $this->yuvarla8(bcmul($netSatir, bcdiv($kdvOran, '100', self::ARA_HESAP_BASAMAK), self::ARA_HESAP_BASAMAK));
                $satirGenel = $this->yuvarla8(bcadd($netSatir, $kdvSatir, self::ARA_HESAP_BASAMAK));
                $netForAra = $netSatir;
                $kdvForTop = $kdvSatir;
            }

            $toplamNet = $this->yuvarla8(bcadd($toplamNet, $netForAra, self::ARA_HESAP_BASAMAK));
            $toplamKdv = $this->yuvarla8(bcadd($toplamKdv, $kdvForTop, self::ARA_HESAP_BASAMAK));

            if (bccomp((string) $kalem->toplam, $satirGenel, self::HESAP_BASAMAK) !== 0) {
                throw new IsKuraliIstisnasi(
                    'Kalem toplamı, KDV hesaplamasına göre beklenen tutarla eşleşmiyor. KDV dahil/hariç seçimini ve satır toplamlarını kontrol edin (satır #'.$kalem->getKey().').'
                );
            }
        }

        $genelInd = $this->yuvarla8((string) $fatura->genel_indirim_tutari);
        $beklenenGenel = $this->yuvarla8(
            bcsub(bcadd($toplamNet, $toplamKdv, self::ARA_HESAP_BASAMAK), $genelInd, self::ARA_HESAP_BASAMAK)
        );
        if (bccomp($beklenenGenel, '0', self::HESAP_BASAMAK) < 0) {
            throw new IsKuraliIstisnasi('Genel toplam sıfırdan küçük olamaz.');
        }

        $tevkifatOrani = $this->yuvarla8((string) ($fatura->tevkifat_orani ?? '0'));
        if (bccomp($tevkifatOrani, '0', 2) < 0 || bccomp($tevkifatOrani, '100', 2) > 0) {
            throw new IsKuraliIstisnasi('Tevkifat oranı 0-100 aralığında olmalıdır.');
        }
        $tevkifatTutari = $this->yuvarla8(
            bcmul(
                $toplamKdv,
                bcdiv($tevkifatOrani, '100', self::ARA_HESAP_BASAMAK),
                self::ARA_HESAP_BASAMAK
            )
        );
        $beklenenOdenecek = $this->yuvarla8(bcsub($beklenenGenel, $tevkifatTutari, self::ARA_HESAP_BASAMAK));
        if (bccomp($beklenenOdenecek, '0', self::HESAP_BASAMAK) < 0) {
            throw new IsKuraliIstisnasi('Tevkifat sonrası ödenecek tutar sıfırdan küçük olamaz.');
        }

        if (bccomp((string) $fatura->ara_toplam, $toplamNet, self::HESAP_BASAMAK) !== 0) {
            throw new IsKuraliIstisnasi(
                'Fatura başlığındaki ara toplam, satırların net tutarlarıyla uyuşmuyor. Başlık veya satırları güncelleyin.'
            );
        }

        if (bccomp((string) $fatura->kdv_toplam, $toplamKdv, self::HESAP_BASAMAK) !== 0) {
            throw new IsKuraliIstisnasi(
                'Fatura başlığındaki KDV toplamı, satır KDV tutarlarının toplamıyla uyuşmuyor.'
            );
        }

        if (bccomp((string) $fatura->genel_toplam, $beklenenGenel, self::HESAP_BASAMAK) !== 0) {
            throw new IsKuraliIstisnasi(
                'Genel toplam, “ara toplam + KDV − genel indirim” ile eşleşmiyor. İndirim ve tutarları kontrol edin.'
            );
        }

        if (bccomp((string) ($fatura->odenecek_tutar ?? '0'), $beklenenOdenecek, self::HESAP_BASAMAK) !== 0) {
            throw new IsKuraliIstisnasi(
                'Ödenecek tutar, tevkifat dahil hesaplanan değerle eşleşmiyor.'
            );
        }
    }

    private function yuvarla8(string $deger): string
    {
        $negatifMi = str_starts_with(trim($deger), '-');
        $yarim = '0.'.str_repeat('0', self::HESAP_BASAMAK).'5';
        $duzeltilmis = $negatifMi
            ? bcsub($deger, $yarim, self::HESAP_BASAMAK + 1)
            : bcadd($deger, $yarim, self::HESAP_BASAMAK + 1);

        return bcadd($duzeltilmis, '0', self::HESAP_BASAMAK);
    }
}
