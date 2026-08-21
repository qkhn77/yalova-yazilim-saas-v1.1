<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\CariHareketi;
use App\Muhasebe\Enumlar\CariHareketBelgeTuru;
use App\Muhasebe\Enumlar\CariHareketDurumu;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Cari ekstre: tarih aralığı, devreden bakiye, kümülatif bakiye (satır sonrası).
 */
class CariEkstreServisi
{
    public function __construct(
        private readonly CariBakiyeServisi $bakiyeServisi,
        private readonly CariHareketFifoEslestirmeServisi $fifoEslestirmeServisi,
    ) {}

    /**
     * Dönem başı öncesi, aynı para biriminde net bakiye (devreden).
     */
    public function devredenBakiye(int $firmaId, int $cariId, string $paraBirimi, Carbon $donemBaslangic): string
    {
        $row = CariHareketi::query()
            ->where('firma_id', $firmaId)
            ->where('cari_id', $cariId)
            ->where('para_birimi', strtoupper($paraBirimi))
            ->where('durum', CariHareketDurumu::Aktif)
            ->where('islem_tarihi', '<', $donemBaslangic->copy()->startOfDay())
            ->selectRaw('COALESCE(SUM(borc),0) - COALESCE(SUM(alacak),0) as net')
            ->first();

        return $this->formatMoney((string) ($row->net ?? 0));
    }

    /**
     * @return array{
     *   devreden: string,
     *   toplam_borc: string,
     *   toplam_alacak: string,
     *   guncel_bakiye: string,
     *   satirlar: Collection<int, array{
     *     hareket: CariHareketi,
     *     net: string,
     *     bakiye_sonrasi: string
     *   }>
     * }
     */
    public function ekstre(
        int $firmaId,
        int $cariId,
        string $paraBirimi,
        Carbon $baslangic,
        Carbon $bitis
    ): array {
        $para = strtoupper($paraBirimi);
        $devreden = $this->devredenBakiye($firmaId, $cariId, $para, $baslangic);

        $hareketler = CariHareketi::query()
            ->where('firma_id', $firmaId)
            ->where('cari_id', $cariId)
            ->where('para_birimi', $para)
            ->where('durum', CariHareketDurumu::Aktif)
            ->whereBetween('islem_tarihi', [$baslangic->copy()->startOfDay(), $bitis->copy()->endOfDay()])
            ->orderBy('islem_tarihi')
            ->orderBy('id')
            ->get();

        $donemHareketIdleri = $hareketler->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $esTop = $this->fifoEslestirmeServisi->toplamEslesenToplamlariHareketBasina($donemHareketIdleri);

        $toplamBorc = '0';
        $toplamAlacak = '0';
        $running = $devreden;
        $satirlar = collect();

        foreach ($hareketler as $h) {
            $borc = $this->formatMoney((string) $h->borc);
            $alacak = $this->formatMoney((string) $h->alacak);
            $toplamBorc = bcadd($toplamBorc, $borc, 2);
            $toplamAlacak = bcadd($toplamAlacak, $alacak, 2);
            $net = $this->bakiyeServisi->netBakiye($borc, $alacak);
            $running = bcadd($running, $net, 2);
            $fifoEk = $this->fifoSatirEkleri($h, $esTop);
            $satirlar->push([
                'hareket' => $h,
                'net' => $net,
                'bakiye_sonrasi' => $running,
                'fifo_acik' => $fifoEk['fifo_acik'],
                'kalan_tutar' => $fifoEk['kalan_tutar'],
            ]);
        }

        return [
            'devreden' => $devreden,
            'toplam_borc' => $toplamBorc,
            'toplam_alacak' => $toplamAlacak,
            'guncel_bakiye' => $this->donemSonuBakiye($devreden, $toplamBorc, $toplamAlacak),
            'satirlar' => $satirlar,
        ];
    }

    private function donemSonuBakiye(string $devreden, string $donemBorc, string $donemAlacak): string
    {
        $donemNet = $this->bakiyeServisi->netBakiye($donemBorc, $donemAlacak);

        return bcadd($devreden, $donemNet, 2);
    }

    private function formatMoney(string $v): string
    {
        return number_format((float) $v, 2, '.', '');
    }

    /**
     * FIFO açık kalem: borç ve/veya alacak tarafında eşlenmemiş tutar.
     *
     * @param  array{borc_taraf: array<int, string>, alacak_taraf: array<int, string>}  $onYuklenmisEslesme
     * @return array{fifo_acik: bool, kalan_tutar: string}
     */
    private function fifoSatirEkleri(CariHareketi $h, array $onYuklenmisEslesme): array
    {
        $hid = (int) $h->getKey();
        $borc = $this->formatMoney((string) $h->borc);
        $alacak = $this->formatMoney((string) $h->alacak);
        $mBorcTaraf = $onYuklenmisEslesme['borc_taraf'][$hid] ?? '0.00';
        $mAlacakTaraf = $onYuklenmisEslesme['alacak_taraf'][$hid] ?? '0.00';

        $parca = [];
        if (bccomp($borc, '0', 2) > 0) {
            $kB = bcsub($borc, $mBorcTaraf, 2);
            if (bccomp($kB, '0', 2) < 0) {
                $kB = '0.00';
            }
            $parca[] = [
                'etiket' => 'B',
                'tutar' => $kB,
            ];
        }
        if (bccomp($alacak, '0', 2) > 0) {
            $kA = bcsub($alacak, $mAlacakTaraf, 2);
            if (bccomp($kA, '0', 2) < 0) {
                $kA = '0.00';
            }
            $parca[] = [
                'etiket' => 'A',
                'tutar' => $kA,
            ];
        }

        $kalanTutar = $parca === []
            ? '0.00'
            : (count($parca) === 1
                ? $parca[0]['tutar']
                : implode(' / ', array_map(
                    static fn (array $p): string => $p['etiket'].':'.$p['tutar'],
                    $parca
                )));

        $fifoAcik = false;
        foreach ($parca as $p) {
            if (bccomp($p['tutar'], '0', 2) > 0) {
                $fifoAcik = true;
                break;
            }
        }

        return [
            'fifo_acik' => $fifoAcik,
            'kalan_tutar' => $kalanTutar,
        ];
    }

    public function belgeTuruEtiket(CariHareketBelgeTuru $tur): string
    {
        return $tur->etiket();
    }
}
