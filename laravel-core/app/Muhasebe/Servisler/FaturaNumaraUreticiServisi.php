<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\FaturaNumaraSayaci;
use App\Muhasebe\Guvenlik\MuhasebeFirmaErisimDenetleyicisi;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Yıl bazlı fatura numarası: transaction + satır kilidi ile yarış koşullarına karşı sıra üretir.
 */
class FaturaNumaraUreticiServisi
{
    public function __construct(
        private readonly MuhasebeFirmaErisimDenetleyicisi $firmaDenetleyicisi,
    ) {}

    /**
     * Örnek format: {yıl}-{6 haneli sıra}, örn. 2026-000042
     */
    public function sonrakiNumarayiUret(int $firmaId, ?int $yil = null): string
    {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);
        $yil = $yil ?? (int) now()->year;

        return DB::transaction(fn (): string => $this->sonrakiNumarayiUretKilitle($firmaId, $yil));
    }

    /**
     * Aynı mantık; dışarıdan zaten açılmış bir transaction içinde çağrılmalıdır (ör. fatura onayı).
     */
    public function sonrakiNumarayiUretKilitle(int $firmaId, ?int $yil = null): string
    {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);
        $yil = $yil ?? (int) now()->year;

        $satir = $this->sayaciKilitleVeGetir($firmaId, $yil);

        $yeni = ((int) $satir->son_sira) + 1;
        $satir->update(['son_sira' => $yeni]);

        return sprintf('%d-%06d', $yil, $yeni);
    }

    private function sayaciKilitleVeGetir(int $firmaId, int $yil): FaturaNumaraSayaci
    {
        $satir = FaturaNumaraSayaci::query()
            ->where('firma_id', $firmaId)
            ->where('yil', $yil)
            ->lockForUpdate()
            ->first();

        if ($satir !== null) {
            return $satir;
        }

        try {
            return FaturaNumaraSayaci::query()->create([
                'firma_id' => $firmaId,
                'yil' => $yil,
                'son_sira' => 0,
            ]);
        } catch (QueryException) {
            $satir = FaturaNumaraSayaci::query()
                ->where('firma_id', $firmaId)
                ->where('yil', $yil)
                ->lockForUpdate()
                ->first();

            if ($satir === null) {
                throw new \RuntimeException('Fatura numara sayacı oluşturulamadı.');
            }

            return $satir;
        }
    }
}
