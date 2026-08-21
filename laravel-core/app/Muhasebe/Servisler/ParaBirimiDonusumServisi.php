<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\DovizKuru;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class ParaBirimiDonusumServisi
{
    private const PARA_BASAMAK = 8;

    /**
     * @return array{
     *   para_birimi:string,
     *   baz_para_birimi:string,
     *   kur:string,
     *   tutar:string,
     *   baz_tutar:string
     * }
     */
    public function tutariBazParaBirimineHazirla(
        int $firmaId,
        string $tutar,
        string $paraBirimi,
        \DateTimeInterface|string|null $tarih = null
    ): array {
        $islemPb = strtoupper(trim($paraBirimi));
        $bazPb = strtoupper((string) config('muhasebe.coklu_para_birimi.baz_para_birimi', 'TRY'));
        $multiAktif = (bool) config('muhasebe.coklu_para_birimi.aktif', false);
        $kurAktif = (bool) config('muhasebe.coklu_para_birimi.kur_donusumu_aktif', false);

        $normalizeTutar = $this->normalizeMoney($tutar);

        if (! $multiAktif) {
            return [
                'para_birimi' => $islemPb,
                'baz_para_birimi' => $islemPb,
                'kur' => '1.00000000',
                'tutar' => $normalizeTutar,
                'baz_tutar' => $normalizeTutar,
            ];
        }

        if ($islemPb === $bazPb) {
            return [
                'para_birimi' => $islemPb,
                'baz_para_birimi' => $bazPb,
                'kur' => '1.00000000',
                'tutar' => $normalizeTutar,
                'baz_tutar' => $normalizeTutar,
            ];
        }

        if (! $kurAktif) {
            throw new IsKuraliIstisnasi('Kur donusumu aktif degil. Islem ve baz para birimi ayni olmalidir.');
        }

        $islemTarihi = $tarih instanceof CarbonInterface
            ? $tarih->toDateString()
            : ($tarih ? (string) Carbon::parse((string) $tarih)->toDateString() : now()->toDateString());

        $kayit = DovizKuru::tenantScopeOlmadan(fn () => DovizKuru::query()
            ->whereIn('tanim_firma_kapsami', [0, $firmaId])
            ->where('kaynak_para_birimi', $islemPb)
            ->where('hedef_para_birimi', $bazPb)
            ->whereDate('tarih', '<=', $islemTarihi)
            // Firma ozel kur varsa her zaman sabit kura oncelik verir.
            ->orderByRaw('CASE WHEN tanim_firma_kapsami = ? THEN 0 ELSE 1 END', [$firmaId])
            ->orderByDesc('tarih')
            ->orderByDesc('id')
            ->first());

        if (! $kayit) {
            throw new IsKuraliIstisnasi($islemPb.' -> '.$bazPb.' icin kur bulunamadi.');
        }

        $kur = $this->normalizeKur((string) $kayit->kur);
        $bazTutar = bcmul($normalizeTutar, $kur, self::PARA_BASAMAK);

        return [
            'para_birimi' => $islemPb,
            'baz_para_birimi' => $bazPb,
            'kur' => $kur,
            'tutar' => $normalizeTutar,
            'baz_tutar' => $this->normalizeMoney($bazTutar),
        ];
    }

    private function normalizeKur(string $kur): string
    {
        $v = (float) $kur;
        if ($v <= 0) {
            throw new IsKuraliIstisnasi('Kur sifirdan buyuk olmalidir.');
        }

        return number_format($v, 8, '.', '');
    }

    private function normalizeMoney(string|int|float $tutar): string
    {
        return number_format((float) $tutar, self::PARA_BASAMAK, '.', '');
    }
}
