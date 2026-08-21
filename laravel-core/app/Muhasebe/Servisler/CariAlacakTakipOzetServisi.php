<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\AlacakPlanTaksiti;
use App\Models\Muhasebe\AlacakPlani;
use App\Models\Muhasebe\AlacakTakipNotu;
use App\Models\Muhasebe\Cari;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class CariAlacakTakipOzetServisi
{
    /** @var array<int,string> */
    private const ACIK_PLAN_DURUMLARI = ['aktif', 'kismi_odendi', 'gecikti'];

    /** @var array<int,string> */
    private const ACIK_TAKSIT_DURUMLARI = ['bekliyor', 'aktif', 'kismi_odendi', 'gecikti'];

    /** @var array<int,string> */
    private const AKTIF_TAKIP_DURUMLARI = ['planlandi', 'ulasilamadi', 'odeme_sozu', 'takip_gerekli'];

    /**
     * @return array<string,mixed>
     */
    public function ozet(Cari $cari, ?string $paraBirimi = null): array
    {
        $paraBirimi = strtoupper((string) ($paraBirimi ?: $cari->para_birimi ?: 'TRY'));
        $bugun = Carbon::today();
        $acikTaksitlerTum = $this->acikTaksitSorgusu($cari)
            ->with(['plan'])
            ->orderBy('vade_tarihi')
            ->orderBy('id')
            ->get();

        $paraOzetleri = $this->paraOzetleri($acikTaksitlerTum, $bugun);

        return [
            'cari' => $cari,
            'para_birimi' => $paraBirimi,
            'para_ozetleri' => $paraOzetleri,
            'ana_para_ozeti' => $paraOzetleri[$paraBirimi] ?? $this->bosParaOzeti($paraBirimi),
            'acik_taksitler' => $acikTaksitlerTum->take(20)->values(),
            'planlar' => $this->planlar($cari),
            'takip_notlari' => $this->takipNotlari($cari),
            'takip_ajandasi' => $this->takipAjandasi($cari),
            'odeme_sozleri' => $this->odemeSozleri($cari),
        ];
    }

    /**
     * @return Collection<int,AlacakPlani>
     */
    private function planlar(Cari $cari): Collection
    {
        return AlacakPlani::query()
            ->where('firma_id', (int) $cari->firma_id)
            ->where('cari_id', (int) $cari->id)
            ->whereIn('durum', self::ACIK_PLAN_DURUMLARI)
            ->withCount([
                'taksitler as acik_taksit_adedi' => fn ($query) => $query
                    ->whereIn('durum', self::ACIK_TAKSIT_DURUMLARI)
                    ->where('kalan_tutar', '>', 0),
            ])
            ->orderBy('son_vade_tarihi')
            ->orderByDesc('id')
            ->limit(10)
            ->get();
    }

    /**
     * @return Collection<int,AlacakTakipNotu>
     */
    private function takipNotlari(Cari $cari): Collection
    {
        return AlacakTakipNotu::query()
            ->where('firma_id', (int) $cari->firma_id)
            ->where('cari_id', (int) $cari->id)
            ->with(['plan', 'taksit', 'olusturan'])
            ->orderByDesc('takip_tarihi')
            ->orderByDesc('id')
            ->limit(15)
            ->get();
    }

    /**
     * @return Collection<int,AlacakTakipNotu>
     */
    private function takipAjandasi(Cari $cari): Collection
    {
        return AlacakTakipNotu::query()
            ->where('firma_id', (int) $cari->firma_id)
            ->where('cari_id', (int) $cari->id)
            ->whereIn('durum', self::AKTIF_TAKIP_DURUMLARI)
            ->whereNotNull('sonraki_takip_tarihi')
            ->where('sonraki_takip_tarihi', '<=', now()->addDays(7)->endOfDay())
            ->with(['plan', 'taksit', 'olusturan'])
            ->orderBy('sonraki_takip_tarihi')
            ->orderBy('id')
            ->limit(10)
            ->get();
    }

    /**
     * @return Collection<int,AlacakTakipNotu>
     */
    private function odemeSozleri(Cari $cari): Collection
    {
        return AlacakTakipNotu::query()
            ->where('firma_id', (int) $cari->firma_id)
            ->where('cari_id', (int) $cari->id)
            ->whereNotNull('odeme_sozu_tarihi')
            ->with(['plan', 'taksit', 'olusturan'])
            ->orderByDesc('odeme_sozu_tarihi')
            ->orderByDesc('id')
            ->limit(10)
            ->get();
    }

    private function acikTaksitSorgusu(Cari $cari)
    {
        return AlacakPlanTaksiti::query()
            ->where('firma_id', (int) $cari->firma_id)
            ->where('cari_id', (int) $cari->id)
            ->whereIn('durum', self::ACIK_TAKSIT_DURUMLARI)
            ->where('kalan_tutar', '>', 0)
            ->whereHas('plan', fn ($query) => $query->whereIn('durum', self::ACIK_PLAN_DURUMLARI));
    }

    /**
     * @param Collection<int,AlacakPlanTaksiti> $taksitler
     * @return array<string,array<string,mixed>>
     */
    private function paraOzetleri(Collection $taksitler, Carbon $bugun): array
    {
        $ozetler = [];

        foreach ($taksitler as $taksit) {
            $plan = $taksit->plan;
            $paraBirimi = strtoupper((string) ($plan?->para_birimi ?: 'TRY'));
            $ozetler[$paraBirimi] ??= $this->bosParaOzeti($paraBirimi);

            $kalan = round((float) $taksit->kalan_tutar, 2);
            $vade = $taksit->vade_tarihi ? Carbon::parse($taksit->vade_tarihi)->startOfDay() : null;

            $ozetler[$paraBirimi]['acik_taksit_adedi']++;
            $ozetler[$paraBirimi]['acik_toplam'] = round((float) $ozetler[$paraBirimi]['acik_toplam'] + $kalan, 2);
            $ozetler[$paraBirimi]['plan_idleri'][(int) $taksit->alacak_plan_id] = true;

            if ($vade) {
                if ($vade->lt($bugun)) {
                    $ozetler[$paraBirimi]['geciken_toplam'] = round((float) $ozetler[$paraBirimi]['geciken_toplam'] + $kalan, 2);
                    $ozetler[$paraBirimi]['geciken_adet']++;
                } elseif ($vade->isSameDay($bugun)) {
                    $ozetler[$paraBirimi]['bugun_toplam'] = round((float) $ozetler[$paraBirimi]['bugun_toplam'] + $kalan, 2);
                } else {
                    $ozetler[$paraBirimi]['gelecek_toplam'] = round((float) $ozetler[$paraBirimi]['gelecek_toplam'] + $kalan, 2);
                }

                $ilk = $ozetler[$paraBirimi]['ilk_vade_tarihi'];
                if ($ilk === null || $vade->lt(Carbon::parse($ilk))) {
                    $ozetler[$paraBirimi]['ilk_vade_tarihi'] = $vade->toDateString();
                }
            }
        }

        foreach ($ozetler as &$ozet) {
            $ozet['plan_adedi'] = count($ozet['plan_idleri']);
            unset($ozet['plan_idleri']);
        }

        ksort($ozetler);

        return $ozetler;
    }

    /**
     * @return array<string,mixed>
     */
    private function bosParaOzeti(string $paraBirimi): array
    {
        return [
            'para_birimi' => $paraBirimi,
            'plan_adedi' => 0,
            'acik_taksit_adedi' => 0,
            'acik_toplam' => 0.0,
            'geciken_toplam' => 0.0,
            'geciken_adet' => 0,
            'bugun_toplam' => 0.0,
            'gelecek_toplam' => 0.0,
            'ilk_vade_tarihi' => null,
            'plan_idleri' => [],
        ];
    }
}
