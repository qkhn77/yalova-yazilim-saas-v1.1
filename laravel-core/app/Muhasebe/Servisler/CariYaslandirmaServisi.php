<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\CariHareketi;
use App\Muhasebe\Enumlar\CariHareketDurumu;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Yaşlandırma: yalnızca FIFO sonrası **açık kalan** satır netleri (borç−alacak, eşleşme düşülmüş).
 *
 * Ölçek: cari başına ayrı bakiye sorgusu ve hareket başına eşleşme sorgusu yerine toplu SQL + batch eşleşme.
 */
class CariYaslandirmaServisi
{
    public function __construct(
        private readonly CariBakiyeServisi $bakiyeServisi,
        private readonly CariHareketFifoEslestirmeServisi $fifoEslestirmeServisi,
    ) {}

    /**
     * @return Collection<int, array{
     *   cari_id: int,
     *   unvan: string,
     *   kod: string|null,
     *   para_birimi: string,
     *   guncel_bakiye: string,
     *   vadesi_gelmemis_net: string,
     *   gun_0_30: string,
     *   gun_30_60: string,
     *   gun_60_90: string,
     *   gun_90_arti: string
     * }>
     */
    public function rapor(int $firmaId, string $paraBirimi = 'TRY'): Collection
    {
        $para = strtoupper($paraBirimi);
        $bugun = Carbon::now()->startOfDay();

        $cariler = Cari::query()
            ->where('firma_id', $firmaId)
            ->orderBy('ad')
            ->get(['id', 'ad', 'kod']);

        $hamNetler = CariHareketi::query()
            ->where('firma_id', $firmaId)
            ->where('durum', CariHareketDurumu::Aktif)
            ->where('para_birimi', $para)
            ->selectRaw('cari_id, COALESCE(SUM(borc), 0) - COALESCE(SUM(alacak), 0) as net')
            ->groupBy('cari_id')
            ->get()
            ->keyBy(fn ($r): int => (int) $r->cari_id);

        $agingHareketler = CariHareketi::query()
            ->where('firma_id', $firmaId)
            ->where('para_birimi', $para)
            ->where('durum', CariHareketDurumu::Aktif)
            ->whereNotNull('vade_tarihi')
            ->orderBy('vade_tarihi')
            ->get(['id', 'cari_id', 'borc', 'alacak', 'vade_tarihi']);

        $agingIds = $agingHareketler->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $es = $this->fifoEslestirmeServisi->toplamEslesenToplamlariHareketBasina($agingIds);
        $borcE = $es['borc_taraf'];
        $alacakE = $es['alacak_taraf'];

        $kova = [];
        foreach ($cariler as $c) {
            $kova[(int) $c->getKey()] = [
                'vadesi_gelmemis_net' => '0',
                'gun_0_30' => '0',
                'gun_30_60' => '0',
                'gun_60_90' => '0',
                'gun_90_arti' => '0',
            ];
        }

        foreach ($agingHareketler as $h) {
            $cid = (int) $h->cari_id;
            if (! isset($kova[$cid])) {
                continue;
            }

            $hid = (int) $h->getKey();
            $borcH = $this->bakiyeServisi->normalizeDecimal((string) $h->borc);
            $alacakH = $this->bakiyeServisi->normalizeDecimal((string) $h->alacak);
            $mB = $borcE[$hid] ?? '0.00';
            $mA = $alacakE[$hid] ?? '0.00';
            $rb = bcsub($borcH, $mB, 2);
            $ra = bcsub($alacakH, $mA, 2);
            $net = bcsub($rb, $ra, 2);

            if (bccomp($net, '0', 2) === 0) {
                continue;
            }

            /** @var Carbon $vade */
            $vade = $h->vade_tarihi->copy()->startOfDay();

            if ($vade->greaterThanOrEqualTo($bugun)) {
                $kova[$cid]['vadesi_gelmemis_net'] = bcadd($kova[$cid]['vadesi_gelmemis_net'], $net, 2);

                continue;
            }

            $gun = (int) $vade->diffInDays($bugun, false);

            if ($gun <= 30) {
                $kova[$cid]['gun_0_30'] = bcadd($kova[$cid]['gun_0_30'], $net, 2);
            } elseif ($gun <= 60) {
                $kova[$cid]['gun_30_60'] = bcadd($kova[$cid]['gun_30_60'], $net, 2);
            } elseif ($gun <= 90) {
                $kova[$cid]['gun_60_90'] = bcadd($kova[$cid]['gun_60_90'], $net, 2);
            } else {
                $kova[$cid]['gun_90_arti'] = bcadd($kova[$cid]['gun_90_arti'], $net, 2);
            }
        }

        return $cariler->map(function (Cari $cari) use ($para, $hamNetler, $kova) {
            $cid = (int) $cari->getKey();
            $ham = $hamNetler->get($cid);
            $guncelBakiye = $ham !== null
                ? bcadd($this->bakiyeServisi->normalizeDecimal((string) $ham->net), '0', 2)
                : '0.00';

            $k = $kova[$cid] ?? [
                'vadesi_gelmemis_net' => '0',
                'gun_0_30' => '0',
                'gun_30_60' => '0',
                'gun_60_90' => '0',
                'gun_90_arti' => '0',
            ];

            return [
                'cari_id' => $cid,
                'unvan' => (string) $cari->ad,
                'kod' => $cari->kod,
                'para_birimi' => $para,
                'guncel_bakiye' => $guncelBakiye,
                'vadesi_gelmemis_net' => $k['vadesi_gelmemis_net'],
                'gun_0_30' => $k['gun_0_30'],
                'gun_30_60' => $k['gun_30_60'],
                'gun_60_90' => $k['gun_60_90'],
                'gun_90_arti' => $k['gun_90_arti'],
            ];
        })->values();
    }
}
