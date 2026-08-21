<?php

namespace App\Muhasebe\Servisler;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AlacakHatirlatmaServisi
{
    /**
     * @return array<string, mixed>
     */
    public function ozet(int $firmaId, int $yaklasanGun = 7, int $limit = 10): array
    {
        $yaklasanGun = max(1, $yaklasanGun);
        $bugun = Carbon::today();
        $yaklasanBitis = $bugun->copy()->addDays($yaklasanGun);

        $geciken = $this->toplamlar($firmaId, null, $bugun->copy()->subDay());
        $bugunVade = $this->toplamlar($firmaId, $bugun, $bugun);
        $yaklasan = $this->toplamlar($firmaId, $bugun->copy()->addDay(), $yaklasanBitis);

        return [
            'firma_id' => $firmaId,
            'yaklasan_gun' => $yaklasanGun,
            'olusturulma' => now()->toDateTimeString(),
            'geciken' => $geciken,
            'bugun' => $bugunVade,
            'yaklasan' => $yaklasan,
            'satirlar' => $this->oncelikliSatirlar($firmaId, $yaklasanGun, $limit),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toplamlar(int $firmaId, ?Carbon $baslangic, ?Carbon $bitis): array
    {
        $query = $this->acikTaksitSorgusu($firmaId);
        if ($baslangic) {
            $query->whereDate('t.vade_tarihi', '>=', $baslangic->toDateString());
        }
        if ($bitis) {
            $query->whereDate('t.vade_tarihi', '<=', $bitis->toDateString());
        }

        $paraToplamlari = $query
            ->selectRaw('plan.para_birimi as para_birimi, COUNT(t.id) as adet, COALESCE(SUM(t.kalan_tutar), 0) as toplam')
            ->groupBy('plan.para_birimi')
            ->orderBy('plan.para_birimi')
            ->get()
            ->map(fn (object $row): array => [
                'para_birimi' => strtoupper((string) ($row->para_birimi ?: 'TRY')),
                'adet' => (int) $row->adet,
                'toplam' => number_format((float) $row->toplam, 2, '.', ''),
            ])
            ->all();

        return [
            'adet' => array_sum(array_map(static fn (array $row): int => (int) $row['adet'], $paraToplamlari)),
            'para_toplamlari' => $paraToplamlari,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function oncelikliSatirlar(int $firmaId, int $yaklasanGun, int $limit): array
    {
        $bugun = Carbon::today();
        $yaklasanBitis = $bugun->copy()->addDays($yaklasanGun);

        return $this->acikTaksitSorgusu($firmaId)
            ->whereDate('t.vade_tarihi', '<=', $yaklasanBitis->toDateString())
            ->selectRaw('c.id as cari_id, c.kod as cari_kod, c.ad as cari_ad, c.telefon as cari_telefon, c.gsm as cari_gsm, c.email as cari_email, plan.para_birimi as para_birimi')
            ->selectRaw('COUNT(t.id) as vade_adedi')
            ->selectRaw('COALESCE(SUM(t.kalan_tutar), 0) as kalan_toplam')
            ->selectRaw('MIN(t.vade_tarihi) as ilk_vade_tarihi')
            ->selectRaw('COALESCE(SUM(CASE WHEN t.vade_tarihi < ? THEN t.kalan_tutar ELSE 0 END), 0) as geciken_toplam', [$bugun->toDateString()])
            ->selectRaw('COALESCE(SUM(CASE WHEN t.vade_tarihi = ? THEN t.kalan_tutar ELSE 0 END), 0) as bugun_toplam', [$bugun->toDateString()])
            ->groupBy('c.id', 'c.kod', 'c.ad', 'c.telefon', 'c.gsm', 'c.email', 'plan.para_birimi')
            ->orderByRaw('MIN(t.vade_tarihi) asc')
            ->orderByDesc('kalan_toplam')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (object $row): array => [
                'cari_id' => (int) $row->cari_id,
                'firma_id' => $firmaId,
                'cari_kod' => (string) ($row->cari_kod ?? ''),
                'cari_ad' => (string) ($row->cari_ad ?? ''),
                'cari_telefon' => (string) ($row->cari_telefon ?? ''),
                'cari_gsm' => (string) ($row->cari_gsm ?? ''),
                'cari_email' => (string) ($row->cari_email ?? ''),
                'para_birimi' => strtoupper((string) ($row->para_birimi ?: 'TRY')),
                'vade_adedi' => (int) $row->vade_adedi,
                'kalan_toplam' => number_format((float) $row->kalan_toplam, 2, '.', ''),
                'geciken_toplam' => number_format((float) $row->geciken_toplam, 2, '.', ''),
                'bugun_toplam' => number_format((float) $row->bugun_toplam, 2, '.', ''),
                'ilk_vade_tarihi' => (string) ($row->ilk_vade_tarihi ?? ''),
            ])
            ->all();
    }

    private function acikTaksitSorgusu(int $firmaId): \Illuminate\Database\Query\Builder
    {
        return DB::table('muhasebe_alacak_plan_taksitleri as t')
            ->join('muhasebe_alacak_planlari as plan', 'plan.id', '=', 't.alacak_plan_id')
            ->join('cariler as c', 'c.id', '=', 't.cari_id')
            ->where('t.firma_id', $firmaId)
            ->where('plan.firma_id', $firmaId)
            ->whereNull('t.deleted_at')
            ->whereNull('plan.deleted_at')
            ->where('t.kalan_tutar', '>', 0)
            ->whereNotIn('t.durum', ['odendi', 'iptal'])
            ->whereIn('plan.durum', ['aktif', 'kismi_odendi', 'gecikti']);
    }
}
