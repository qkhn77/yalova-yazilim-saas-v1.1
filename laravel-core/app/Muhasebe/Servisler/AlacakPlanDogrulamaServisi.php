<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\AlacakPlani;
use App\Models\Muhasebe\AlacakPlanTaksiti;
use App\Models\Muhasebe\AlacakTahsilatEslesmesi;
use Illuminate\Support\Facades\DB;

class AlacakPlanDogrulamaServisi
{
    /**
     * @return array<string, mixed>
     */
    public function kontrolEt(?int $firmaId = null, int $limit = 5000): array
    {
        $limit = max(1, $limit);
        $sorunlar = [];

        $planSorgusu = AlacakPlani::query()
            ->withoutGlobalScopes()
            ->when($firmaId, fn ($query) => $query->where('firma_id', $firmaId))
            ->orderBy('id')
            ->limit($limit);

        /** @var AlacakPlani $plan */
        foreach ($planSorgusu->cursor() as $plan) {
            $this->planSorunlariniEkle($plan, $sorunlar);
        }

        $this->yetimTaksitSorunlariniEkle($firmaId, $sorunlar, $limit);
        $this->yetimEslesmeSorunlariniEkle($firmaId, $sorunlar, $limit);
        $this->ciftAktifKaynakSorunlariniEkle($firmaId, $sorunlar, $limit);

        return [
            'firma_id' => $firmaId,
            'kontrol_edilen_plan' => (int) $planSorgusu->count(),
            'toplam_sorun' => count($sorunlar),
            'sorunlar' => $sorunlar,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $sorunlar
     */
    private function planSorunlariniEkle(AlacakPlani $plan, array &$sorunlar): void
    {
        $planId = (int) $plan->getKey();
        $taksitOzetSorgusu = AlacakPlanTaksiti::query()
            ->withoutGlobalScopes()
            ->where('alacak_plan_id', $planId);
        if ((string) $plan->durum !== 'iptal') {
            $taksitOzetSorgusu->where('durum', '!=', 'iptal');
        }

        $taksitOzet = $taksitOzetSorgusu
            ->selectRaw('COUNT(*) as adet')
            ->selectRaw('COALESCE(SUM(tutar), 0) as taksit_toplam')
            ->selectRaw('COALESCE(SUM(odenen_tutar), 0) as taksit_odenen')
            ->selectRaw('COALESCE(SUM(kalan_tutar), 0) as taksit_kalan')
            ->first();

        $taksitAdedi = (int) ($taksitOzet?->adet ?? 0);
        if ($taksitAdedi < 1 && (string) $plan->durum !== 'iptal') {
            $this->sorunEkle($sorunlar, 'plan_taksitsiz', $plan, 'Aktif planin taksiti yok.');
        }

        $planlanan = $this->decimal($plan->planlanan_tutar);
        $taksitToplam = $this->decimal($taksitOzet?->taksit_toplam ?? 0);
        $taksitOdenen = $this->decimal($taksitOzet?->taksit_odenen ?? 0);
        $taksitKalan = $this->decimal($taksitOzet?->taksit_kalan ?? 0);
        $beklenenOdenen = bcadd($this->decimal($plan->pesinat_tutari), $taksitOdenen, 2);

        if ((string) $plan->durum !== 'iptal' && $taksitAdedi > 0 && $this->farkVarMi($planlanan, $taksitToplam)) {
            $this->sorunEkle($sorunlar, 'plan_taksit_toplami_uyumsuz', $plan, 'Planlanan tutar ile taksit toplami uyusmuyor.', [
                'planlanan' => $planlanan,
                'taksit_toplam' => $taksitToplam,
            ]);
        }

        if ($this->farkVarMi($this->decimal($plan->kalan_tutar), $taksitKalan)) {
            $this->sorunEkle($sorunlar, 'plan_kalan_uyumsuz', $plan, 'Plan kalan tutari ile taksit kalan toplami uyusmuyor.', [
                'plan_kalan' => $this->decimal($plan->kalan_tutar),
                'taksit_kalan' => $taksitKalan,
            ]);
        }

        if ($this->farkVarMi($this->decimal($plan->odenen_tutar), $beklenenOdenen)) {
            $this->sorunEkle($sorunlar, 'plan_odenen_uyumsuz', $plan, 'Plan odenen tutari ile pesinat + taksit odenen uyusmuyor.', [
                'plan_odenen' => $this->decimal($plan->odenen_tutar),
                'beklenen_odenen' => $beklenenOdenen,
            ]);
        }

        $eslesmeToplami = $this->decimal(AlacakTahsilatEslesmesi::query()
            ->withoutGlobalScopes()
            ->where('alacak_plan_id', $planId)
            ->sum('tutar'));
        if ($this->farkVarMi($taksitOdenen, $eslesmeToplami)) {
            $this->sorunEkle($sorunlar, 'plan_tahsilat_eslesme_uyumsuz', $plan, 'Taksit odenen toplami ile tahsilat eslesmeleri uyusmuyor.', [
                'taksit_odenen' => $taksitOdenen,
                'eslesme_toplami' => $eslesmeToplami,
            ]);
        }

        $cariParaBirimi = DB::table('cariler')
            ->where('id', (int) $plan->cari_id)
            ->value('para_birimi');
        if ($cariParaBirimi && strtoupper((string) $cariParaBirimi) !== strtoupper((string) $plan->para_birimi)) {
            $this->sorunEkle($sorunlar, 'plan_cari_para_birimi_uyumsuz', $plan, 'Plan para birimi cari para birimi ile uyusmuyor.', [
                'plan_para_birimi' => strtoupper((string) $plan->para_birimi),
                'cari_para_birimi' => strtoupper((string) $cariParaBirimi),
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $sorunlar
     */
    private function yetimTaksitSorunlariniEkle(?int $firmaId, array &$sorunlar, int $limit): void
    {
        DB::table('muhasebe_alacak_plan_taksitleri as t')
            ->leftJoin('muhasebe_alacak_planlari as plan', 'plan.id', '=', 't.alacak_plan_id')
            ->whereNull('plan.id')
            ->when($firmaId, fn ($query) => $query->where('t.firma_id', $firmaId))
            ->select(['t.id', 't.firma_id', 't.alacak_plan_id'])
            ->limit($limit)
            ->get()
            ->each(function (object $row) use (&$sorunlar): void {
                $sorunlar[] = [
                    'kod' => 'yetim_taksit',
                    'firma_id' => (int) $row->firma_id,
                    'plan_id' => (int) $row->alacak_plan_id,
                    'kaynak_id' => (int) $row->id,
                    'detay' => 'Plan kaydi olmayan taksit bulundu.',
                ];
            });
    }

    /**
     * @param array<int, array<string, mixed>> $sorunlar
     */
    private function yetimEslesmeSorunlariniEkle(?int $firmaId, array &$sorunlar, int $limit): void
    {
        DB::table('muhasebe_alacak_tahsilat_eslesmeleri as e')
            ->leftJoin('muhasebe_alacak_planlari as plan', 'plan.id', '=', 'e.alacak_plan_id')
            ->leftJoin('muhasebe_alacak_plan_taksitleri as t', 't.id', '=', 'e.alacak_plan_taksiti_id')
            ->leftJoin('finans_hareketleri as f', 'f.id', '=', 'e.finans_hareketi_id')
            ->where(function ($query): void {
                $query->whereNull('plan.id')
                    ->orWhereNull('t.id')
                    ->orWhereNull('f.id');
            })
            ->when($firmaId, fn ($query) => $query->where('e.firma_id', $firmaId))
            ->select(['e.id', 'e.firma_id', 'e.alacak_plan_id', 'e.alacak_plan_taksiti_id', 'e.finans_hareketi_id'])
            ->limit($limit)
            ->get()
            ->each(function (object $row) use (&$sorunlar): void {
                $sorunlar[] = [
                    'kod' => 'yetim_tahsilat_eslesmesi',
                    'firma_id' => (int) $row->firma_id,
                    'plan_id' => (int) $row->alacak_plan_id,
                    'kaynak_id' => (int) $row->id,
                    'detay' => 'Plan, taksit veya finans hareketi eksik tahsilat eslesmesi bulundu.',
                    'ek' => [
                        'taksit_id' => (int) $row->alacak_plan_taksiti_id,
                        'finans_hareketi_id' => (int) $row->finans_hareketi_id,
                    ],
                ];
            });
    }

    /**
     * @param array<int, array<string, mixed>> $sorunlar
     */
    private function ciftAktifKaynakSorunlariniEkle(?int $firmaId, array &$sorunlar, int $limit): void
    {
        DB::table('muhasebe_alacak_planlari')
            ->whereIn('durum', ['aktif', 'kismi_odendi', 'gecikti'])
            ->whereNotNull('kaynak_id')
            ->whereNull('deleted_at')
            ->when($firmaId, fn ($query) => $query->where('firma_id', $firmaId))
            ->selectRaw('firma_id, kaynak_turu, kaynak_id, COUNT(*) as adet')
            ->groupBy('firma_id', 'kaynak_turu', 'kaynak_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit($limit)
            ->get()
            ->each(function (object $row) use (&$sorunlar): void {
                $sorunlar[] = [
                    'kod' => 'cift_aktif_kaynak_plani',
                    'firma_id' => (int) $row->firma_id,
                    'plan_id' => null,
                    'kaynak_id' => (int) $row->kaynak_id,
                    'detay' => 'Ayni kaynak icin birden fazla aktif alacak plani var.',
                    'ek' => [
                        'kaynak_turu' => (string) $row->kaynak_turu,
                        'adet' => (int) $row->adet,
                    ],
                ];
            });
    }

    /**
     * @param array<int, array<string, mixed>> $sorunlar
     * @param array<string, mixed> $ek
     */
    private function sorunEkle(array &$sorunlar, string $kod, AlacakPlani $plan, string $detay, array $ek = []): void
    {
        $sorunlar[] = [
            'kod' => $kod,
            'firma_id' => (int) $plan->firma_id,
            'plan_id' => (int) $plan->getKey(),
            'kaynak_id' => (int) ($plan->kaynak_id ?? 0),
            'detay' => $detay,
            'ek' => $ek,
        ];
    }

    private function decimal(mixed $tutar): string
    {
        return number_format((float) $tutar, 2, '.', '');
    }

    private function farkVarMi(string $sol, string $sag): bool
    {
        return bccomp($this->decimal($sol), $this->decimal($sag), 2) !== 0;
    }
}
