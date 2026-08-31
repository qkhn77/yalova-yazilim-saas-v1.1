<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\AlacakPlani;
use App\Models\Muhasebe\AlacakTahsilatEslesmesi;
use App\Models\Muhasebe\BarkodluSatis;
use App\Models\Muhasebe\FinansHareketi;
use App\Muhasebe\Enumlar\FinansHareketDurumu;
use Illuminate\Support\Collection;

final class BarkodluSatisAlacakOzetServisi
{
    /** @var array<int,string> */
    private const PLAN_OZET_DURUMLARI = ['aktif', 'kismi_odendi', 'gecikti', 'odendi'];

    /** @var array<int,string> */
    private const ACIK_PLAN_DURUMLARI = ['aktif', 'kismi_odendi', 'gecikti'];

    /**
     * @return array<string,mixed>
     */
    public function ozet(BarkodluSatis $satis): array
    {
        $paraBirimi = strtoupper((string) ($satis->para_birimi ?: $satis->cari?->para_birimi ?: 'TRY'));
        $toplamTutar = $this->tutar($satis->genel_toplam ?? 0);
        $plan = $this->sonPlan($satis);
        $dogrudanTahsilatlar = $this->dogrudanTahsilatlar($satis);
        $dogrudanTahsilatToplami = $this->tutar($dogrudanTahsilatlar->sum('tutar'));

        $planToplamTutar = $plan ? $this->tutar($plan->toplam_tutar) : 0.0;
        $planKalanTutar = $plan && in_array((string) $plan->durum, self::ACIK_PLAN_DURUMLARI, true)
            ? $this->tutar($plan->kalan_tutar)
            : 0.0;
        $planOdenenTutar = $plan ? $this->tutar($plan->odenen_tutar) : 0.0;
        $tahsilatToplami = max($dogrudanTahsilatToplami, $planOdenenTutar);
        $dogrudanBakiye = max(0.0, round($toplamTutar - $dogrudanTahsilatToplami, 2));
        $plansizKalanTutar = max(0.0, round($toplamTutar - max($dogrudanTahsilatToplami, $planToplamTutar), 2));
        $finansalAcikTutar = $plan ? $planKalanTutar : max(0.0, round($toplamTutar - $tahsilatToplami, 2));
        $ilkAcikTaksit = $plan
            ? $plan->taksitler()
                ->whereIn('durum', self::ACIK_PLAN_DURUMLARI)
                ->where('kalan_tutar', '>', 0)
                ->orderBy('vade_tarihi')
                ->orderBy('sira_no')
                ->first()
            : null;

        $iptalMi = (string) ($satis->durum ?? '') === 'iptal';
        if ($iptalMi) {
            $finansalAcikTutar = 0.0;
            $plansizKalanTutar = 0.0;
        }

        return [
            'satis' => $satis,
            'toplam_tutar' => $toplamTutar,
            'para_birimi' => $paraBirimi,
            'dogrudan_tahsilat_toplami' => $dogrudanTahsilatToplami,
            'tahsilat_toplami' => $tahsilatToplami,
            'dogrudan_bakiye_tutar' => $dogrudanBakiye,
            'finansal_acik_tutar' => $finansalAcikTutar,
            'plansiz_kalan_tutar' => $plansizKalanTutar,
            'plan' => $plan,
            'plan_toplam_tutar' => $planToplamTutar,
            'plan_kalan_tutar' => $planKalanTutar,
            'plan_odenen_tutar' => $planOdenenTutar,
            'ilk_acik_taksit' => $ilkAcikTaksit,
            'taksitler' => $plan ? $plan->taksitler()->orderBy('sira_no')->get() : collect(),
            'plan_tahsilatlari' => $plan ? $this->planTahsilatlari($plan) : collect(),
            'dogrudan_tahsilatlar' => $dogrudanTahsilatlar,
            'durum' => $iptalMi ? 'kapali' : $this->durum($plan, $finansalAcikTutar, $plansizKalanTutar),
            'durum_etiketi' => $iptalMi ? 'Tam' : $this->durumEtiketi($plan, $finansalAcikTutar, $plansizKalanTutar),
        ];
    }

    public function tahsilatDurumuEtiketi(BarkodluSatis $satis): string
    {
        return (string) ($this->ozet($satis)['durum_etiketi'] ?? 'Yok');
    }

    public function tahsilatDurumuRenk(BarkodluSatis $satis): string
    {
        return match ((string) ($this->ozet($satis)['durum'] ?? 'kapali')) {
            'kapali' => 'success',
            'planli_acik' => 'warning',
            'plansiz_acik', 'acik' => 'danger',
            default => 'gray',
        };
    }

    private function sonPlan(BarkodluSatis $satis): ?AlacakPlani
    {
        if (! $satis->exists) {
            return null;
        }

        return AlacakPlani::query()
            ->where('firma_id', (int) $satis->firma_id)
            ->where('kaynak_turu', 'barkodlu_satis')
            ->where('kaynak_id', (int) $satis->getKey())
            ->whereIn('durum', self::PLAN_OZET_DURUMLARI)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return Collection<int,FinansHareketi>
     */
    private function dogrudanTahsilatlar(BarkodluSatis $satis): Collection
    {
        if (! $satis->exists) {
            return collect();
        }

        if ($satis->relationLoaded('finansHareketleri')) {
            return $satis->finansHareketleri
                ->filter(fn (FinansHareketi $hareket): bool => (string) ($hareket->durum?->value ?? $hareket->durum ?? '') === FinansHareketDurumu::Aktif->value)
                ->sortByDesc('id')
                ->values();
        }

        return $satis->finansHareketleri()
            ->where('durum', FinansHareketDurumu::Aktif->value)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int,AlacakTahsilatEslesmesi>
     */
    private function planTahsilatlari(AlacakPlani $plan): Collection
    {
        return $plan->tahsilatEslesmeleri()
            ->with(['taksit', 'finansHareketi'])
            ->orderByDesc('tarih')
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }

    private function durum(?AlacakPlani $plan, float $finansalAcikTutar, float $plansizKalanTutar): string
    {
        if ($plansizKalanTutar > 0.009) {
            return 'plansiz_acik';
        }

        if ($plan && $finansalAcikTutar > 0.009) {
            return 'planli_acik';
        }

        return $finansalAcikTutar > 0.009 ? 'acik' : 'kapali';
    }

    private function durumEtiketi(?AlacakPlani $plan, float $finansalAcikTutar, float $plansizKalanTutar): string
    {
        if ($plansizKalanTutar > 0.009) {
            return 'Plansız Açık';
        }

        if ($plan && $finansalAcikTutar > 0.009) {
            return 'Planlı Açık';
        }

        if ($finansalAcikTutar > 0.009) {
            return 'Eksik';
        }

        return 'Tam';
    }

    private function tutar(mixed $deger): float
    {
        return round((float) str_replace(',', '.', (string) $deger), 2);
    }
}
