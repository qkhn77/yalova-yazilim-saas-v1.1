<?php

namespace App\Muhasebe\Servisler;

/**
 * POS komisyonu ve valör/bloke için tutar bölüştürme (saf matematik; mutabakat kuralları işletmeye göre genişletilir).
 */
class PosKomisyonHesaplamaServisi
{
    /**
     * Brüt tutar üzerinden komisyon (yüzde).
     */
    public function komisyonTutariHesapla(string $brutTutar, string $komisyonOraniYuzde): string
    {
        return bcadd(
            bcmul($brutTutar, bcdiv($komisyonOraniYuzde, '100', 8), 4),
            '0',
            2
        );
    }

    /**
     * Valör sonrası net tahsilat = brüt - komisyon (basit model; bloke günü ayrı takvim tablosu ile bağlanabilir).
     */
    public function netTahsilatHesapla(string $brutTutar, string $komisyonTutari): string
    {
        return bcadd(bcsub($brutTutar, $komisyonTutari, 4), '0', 2);
    }

    /**
     * Bloke süresi için yer tutucu: gerçek valör takvimi entegrasyonu STEP 11+.
     *
     * @return array{tahmini_netlesme_tarihi: \DateTimeImmutable, not: string}
     */
    public function blokeValorumHazirligi(\DateTimeInterface $islemTarihi, int $blokeGunSayisi): array
    {
        $t = \DateTimeImmutable::createFromInterface($islemTarihi);

        return [
            'tahmini_netlesme_tarihi' => $t->modify('+'.$blokeGunSayisi.' days'),
            'not' => 'Bloke/valör takvimi henüz banka/POS mutabakat modülüne bağlı değildir.',
        ];
    }
}
