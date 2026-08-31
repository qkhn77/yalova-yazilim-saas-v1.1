<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\FaturaFinansKapama;
use App\Models\Muhasebe\KurFarkiHareketi;

class KurFarkiHareketServisi
{
    public function kapamadanOlustur(FaturaFinansKapama $kapama): ?KurFarkiHareketi
    {
        $tutar = (string) ($kapama->kur_farki_tutari ?? '0');
        if (bccomp($tutar, '0', 8) === 0) {
            return null;
        }

        $faturaYonu = $kapama->fatura?->tur?->cariYonu();
        $pozitifFark = bccomp($tutar, '0', 8) === 1;
        $kazanc = $faturaYonu === 'alacak' ? $pozitifFark : ! $pozitifFark;

        return KurFarkiHareketi::query()->firstOrCreate(
            ['fatura_finans_kapama_id' => $kapama->getKey()],
            [
                'firma_id' => $kapama->firma_id,
                'fatura_id' => $kapama->fatura_id,
                'finans_hareket_id' => $kapama->finans_hareket_id,
                'tutar' => $tutar,
                'yon' => $kazanc ? 'kazanc' : 'zarar',
                'para_birimi' => (string) ($kapama->baz_para_birimi ?: 'TRY'),
                'durum' => 'aktif',
                'aciklama' => 'Fatura ödeme/tahsilat kur farkı · kapama #'.$kapama->getKey(),
            ]
        );
    }

    public function finansKurFarklariniIptalEt(int $finansHareketId): int
    {
        return KurFarkiHareketi::query()
            ->where('finans_hareket_id', $finansHareketId)
            ->where('durum', 'aktif')
            ->update(['durum' => 'iptal']);
    }

    /** @return array{kazanc:string,zarar:string,net:string} */
    public function ozet(int $firmaId, ?string $baslangic = null, ?string $bitis = null): array
    {
        $satirlar = KurFarkiHareketi::query()
            ->where('firma_id', $firmaId)
            ->where('durum', 'aktif')
            ->when($baslangic, fn ($q) => $q->whereDate('created_at', '>=', $baslangic))
            ->when($bitis, fn ($q) => $q->whereDate('created_at', '<=', $bitis))
            ->get(['tutar', 'yon']);

        $kazanc = '0.00000000';
        $zarar = '0.00000000';
        foreach ($satirlar as $satir) {
            $mutlak = number_format(abs((float) $satir->tutar), 8, '.', '');
            if ($satir->yon === 'kazanc') {
                $kazanc = bcadd($kazanc, $mutlak, 8);
            } else {
                $zarar = bcadd($zarar, $mutlak, 8);
            }
        }

        return [
            'kazanc' => $kazanc,
            'zarar' => $zarar,
            'net' => bcsub($kazanc, $zarar, 8),
        ];
    }
}
