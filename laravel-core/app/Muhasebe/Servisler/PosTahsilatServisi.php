<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\CariHareketi;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\PosHareketi;

/**
 * POS tahsilat akışı — POS satırı + finans + cari borç (tahsilat).
 */
class PosTahsilatServisi
{
    public function __construct(
        private readonly FinansHareketServisi $finansHareketServisi,
    ) {}

    /**
     * @return array{finans: FinansHareketi, pos: PosHareketi, cari: CariHareketi}
     */
    public function tahsilatKaydet(
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

    /**
     * @return array{finans: FinansHareketi, pos: PosHareketi, cari: CariHareketi}
     */
    public function tahsilatKomisyonluKaydet(
        int $firmaId,
        int $cariId,
        int $posHesapId,
        string $brutTutar,
        string $komisyonTutari,
        string $paraBirimi,
        \DateTimeInterface|string $tarih,
        ?string $aciklama = null,
        ?string $referansTuru = null,
        ?int $referansId = null,
        ?string $slipNo = null,
        ?string $provizyonNo = null,
        ?string $posDetayAciklama = null,
    ): array {
        return $this->finansHareketServisi->tahsilatPosKomisyonluKaydet(
            $firmaId,
            $cariId,
            $posHesapId,
            $brutTutar,
            $komisyonTutari,
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
