<?php

namespace App\Muhasebe\Servisler;

/**
 * Banka tarafı tahsilat/ödeme ve kasa ile virman — tek giriş noktası (FinansHareketServisi üzerinden).
 */
class BankaHareketServisi
{
    public function __construct(
        private readonly FinansHareketServisi $finansHareketServisi,
    ) {}

    public function tahsilatBankadanKaydet(
        int $firmaId,
        int $cariId,
        int $bankaHesapId,
        string $tutar,
        string $paraBirimi,
        \DateTimeInterface|string $tarih,
        ?string $aciklama = null,
        ?string $referansTuru = null,
        ?int $referansId = null,
        ?string $dekontNo = null,
        ?string $islemReferansi = null,
        ?string $bankaDetayAciklama = null,
    ): array {
        return $this->finansHareketServisi->tahsilatBankadanKaydet(
            $firmaId,
            $cariId,
            $bankaHesapId,
            $tutar,
            $paraBirimi,
            $tarih,
            $aciklama,
            $referansTuru,
            $referansId,
            $dekontNo,
            $islemReferansi,
            $bankaDetayAciklama,
        );
    }

    public function odemeBankadanKaydet(
        int $firmaId,
        int $cariId,
        int $bankaHesapId,
        string $tutar,
        string $paraBirimi,
        \DateTimeInterface|string $tarih,
        ?string $aciklama = null,
        ?string $referansTuru = null,
        ?int $referansId = null,
        ?string $dekontNo = null,
        ?string $islemReferansi = null,
        ?string $bankaDetayAciklama = null,
    ): array {
        return $this->finansHareketServisi->odemeBankadanKaydet(
            $firmaId,
            $cariId,
            $bankaHesapId,
            $tutar,
            $paraBirimi,
            $tarih,
            $aciklama,
            $referansTuru,
            $referansId,
            $dekontNo,
            $islemReferansi,
            $bankaDetayAciklama,
        );
    }

    public function virmanKasaBankaKaydet(
        int $firmaId,
        int $kasaHesapId,
        int $bankaHesapId,
        string $tutar,
        string $paraBirimi,
        \DateTimeInterface|string $tarih,
        ?string $aciklama = null,
    ): array {
        return $this->finansHareketServisi->virmanKasaBankaKaydet(
            $firmaId,
            $kasaHesapId,
            $bankaHesapId,
            $tutar,
            $paraBirimi,
            $tarih,
            $aciklama,
        );
    }

    public function virmanBankaKasayaKaydet(
        int $firmaId,
        int $bankaHesapId,
        int $kasaHesapId,
        string $tutar,
        string $paraBirimi,
        \DateTimeInterface|string $tarih,
        ?string $aciklama = null,
    ): array {
        return $this->finansHareketServisi->virmanBankaKasayaKaydet(
            $firmaId,
            $bankaHesapId,
            $kasaHesapId,
            $tutar,
            $paraBirimi,
            $tarih,
            $aciklama,
        );
    }
}
