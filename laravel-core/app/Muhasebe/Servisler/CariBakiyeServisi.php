<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\CariHareketi;
use App\Muhasebe\Enumlar\CariHareketDurumu;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cari bakiyesi: bakiye = toplam_borc - toplam_alacak (para birimi bazlı).
 *
 * {@see paraBirimiOzetleri} ham satır toplamlarıdır (geriye dönük uyumlu).
 * {@see paraBirimiOzetleriAcikKalem} FIFO sonrası kalan (eşlenmemiş) tutarların toplamıdır;
 * tam eşleşmede bakiye ham ile aynı olmalıdır.
 */
class CariBakiyeServisi
{
    public function __construct(
        private readonly CariHareketFifoEslestirmeServisi $fifoEslestirmeServisi,
    ) {}

    /**
     * Firma (ve isteğe bağlı cari) için para birimi bazlı özet.
     *
     * @return Collection<int, object{
     *   para_birimi: string,
     *   toplam_borc: string,
     *   toplam_alacak: string,
     *   bakiye: string
     * }>
     */
    public function paraBirimiOzetleri(int $firmaId, ?int $cariId = null): Collection
    {
        $q = CariHareketi::query()
            ->where('firma_id', $firmaId)
            ->where('durum', CariHareketDurumu::Aktif);

        if ($cariId !== null) {
            $q->where('cari_id', $cariId);
        }

        $satirlar = $q
            ->select([
                'para_birimi',
                DB::raw('COALESCE(SUM(borc), 0) as toplam_borc'),
                DB::raw('COALESCE(SUM(alacak), 0) as toplam_alacak'),
            ])
            ->groupBy('para_birimi')
            ->get();

        return $satirlar->map(function ($r) {
            $borc = (string) $r->toplam_borc;
            $alacak = (string) $r->toplam_alacak;
            $bakiye = $this->netBakiye($borc, $alacak);

            return (object) [
                'para_birimi' => (string) $r->para_birimi,
                'toplam_borc' => $borc,
                'toplam_alacak' => $alacak,
                'bakiye' => $bakiye,
            ];
        });
    }

    /**
     * Eşleşmemiş (açık) borç ve alacak tutarlarının para birimi bazlı toplamı.
     *
     * @return Collection<int, object{
     *   para_birimi: string,
     *   toplam_borc: string,
     *   toplam_alacak: string,
     *   bakiye: string
     * }>
     */
    public function paraBirimiOzetleriAcikKalem(int $firmaId, ?int $cariId = null): Collection
    {
        $q = CariHareketi::query()
            ->where('firma_id', $firmaId)
            ->where('durum', CariHareketDurumu::Aktif);

        if ($cariId !== null) {
            $q->where('cari_id', $cariId);
        }

        $satirlar = $q->get(['id', 'para_birimi', 'borc', 'alacak']);
        $idList = $satirlar->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $es = $this->fifoEslestirmeServisi->toplamEslesenToplamlariHareketBasina($idList);
        $borcEs = $es['borc_taraf'];
        $alacakEs = $es['alacak_taraf'];

        $gruplar = [];

        foreach ($satirlar as $h) {
            $p = (string) $h->para_birimi;
            if (! isset($gruplar[$p])) {
                $gruplar[$p] = ['acik_borc' => '0', 'acik_alacak' => '0'];
            }

            $hid = (int) $h->getKey();
            $rb = $this->normalizeDecimal((string) $h->borc);
            $ra = $this->normalizeDecimal((string) $h->alacak);
            $mB = $borcEs[$hid] ?? '0.00';
            $mA = $alacakEs[$hid] ?? '0.00';

            $acikB = bcsub($rb, $mB, 2);
            if (bccomp($acikB, '0', 2) < 0) {
                $acikB = '0.00';
            }
            $acikA = bcsub($ra, $mA, 2);
            if (bccomp($acikA, '0', 2) < 0) {
                $acikA = '0.00';
            }

            $gruplar[$p]['acik_borc'] = bcadd($gruplar[$p]['acik_borc'], $acikB, 2);
            $gruplar[$p]['acik_alacak'] = bcadd($gruplar[$p]['acik_alacak'], $acikA, 2);
        }

        return collect($gruplar)->map(function (array $v, string $para) {
            $bakiye = bcsub($v['acik_borc'], $v['acik_alacak'], 2);

            return (object) [
                'para_birimi' => $para,
                'toplam_borc' => $v['acik_borc'],
                'toplam_alacak' => $v['acik_alacak'],
                'bakiye' => $bakiye,
            ];
        })->values();
    }

    /**
     * Ham bakiye ile açık kalem bakiyesinin farkı (mutabakat); sıfır olmalı.
     */
    /**
     * Para birimi başına ham bakiye − açık kalem bakiyesi. Tümü sıfır olmalı.
     *
     * @return array<string, string> para_birimi => fark
     */
    public function fifoHamBakiyeFarklari(int $firmaId, ?int $cariId = null): array
    {
        $ham = $this->paraBirimiOzetleri($firmaId, $cariId);
        $acik = $this->paraBirimiOzetleriAcikKalem($firmaId, $cariId);

        $paralar = $ham->pluck('para_birimi')->merge($acik->pluck('para_birimi'))->unique()->values()->all();

        $farklar = [];
        foreach ($paralar as $p) {
            $h = $ham->firstWhere('para_birimi', $p);
            $a = $acik->firstWhere('para_birimi', $p);
            $bh = $h ? (string) $h->bakiye : '0.00';
            $ba = $a ? (string) $a->bakiye : '0.00';
            $farklar[$p] = bcsub($bh, $ba, 2);
        }

        return $farklar;
    }

    public function netBakiye(string|float $borc, string|float $alacak): string
    {
        return bcsub($this->normalizeDecimal($borc), $this->normalizeDecimal($alacak), 2);
    }

    public function normalizeDecimal(string|float $v): string
    {
        if (is_string($v)) {
            return $v === '' ? '0' : $v;
        }

        return number_format((float) $v, 2, '.', '');
    }
}
