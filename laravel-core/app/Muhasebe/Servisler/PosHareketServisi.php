<?php

namespace App\Muhasebe\Servisler;

/**
 * POS satır hareketleri ve cari bağlantısı — uygulama {@see FinansHareketServisi} üzerinden.
 */
class PosHareketServisi
{
    public function __construct(
        private readonly FinansHareketServisi $finansHareketServisi,
    ) {}

    public function tahsilatPosKaydet(
        int $firmaId,
        int $cariId,
        int $posHesapId,
        string $tutar,
        string $paraBirimi,
        \DateTimeInterface|string $tarih,
        ?string $aciklama = null,
        ?string $referansTuru = null,
        ?int $referansId = null,
        ?string $slipNo = null,
        ?string $provizyonNo = null,
        ?string $posDetayAciklama = null,
    ): array {
        return $this->finansHareketServisi->tahsilatPosKaydet(
            $firmaId,
            $cariId,
            $posHesapId,
            $tutar,
            $paraBirimi,
            $tarih,
            $aciklama,
            $referansTuru,
            $referansId,
            $slipNo,
            $provizyonNo,
            $posDetayAciklama,
        );
    }

    public function posIadeKaydet(
        int $firmaId,
        int $cariId,
        int $posHesapId,
        string $tutar,
        string $paraBirimi,
        \DateTimeInterface|string $tarih,
        ?string $aciklama = null,
        ?string $referansTuru = null,
        ?int $referansId = null,
        ?string $slipNo = null,
        ?string $provizyonNo = null,
        ?string $posDetayAciklama = null,
    ): array {
        return $this->finansHareketServisi->posIadeKaydet(
            $firmaId,
            $cariId,
            $posHesapId,
            $tutar,
            $paraBirimi,
            $tarih,
            $aciklama,
            $referansTuru,
            $referansId,
            $slipNo,
            $provizyonNo,
            $posDetayAciklama,
        );
    }
}
