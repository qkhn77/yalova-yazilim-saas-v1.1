<?php

namespace App\TeknikServis\Servisler;

use App\Models\Muhasebe\AlacakPlani;
use App\Models\Muhasebe\AlacakTahsilatEslesmesi;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Models\TeknikServis\TeknikServisTahsilati;
use Illuminate\Support\Collection;

final class TeknikServisAlacakOzetServisi
{
    /** @var array<int,string> */
    private const PLAN_OZET_DURUMLARI = ['aktif', 'kismi_odendi', 'gecikti', 'odendi'];

    /** @var array<int,string> */
    private const ACIK_PLAN_DURUMLARI = ['aktif', 'kismi_odendi', 'gecikti'];

    /**
     * @param array<string,mixed> $taslak
     * @return array<string,mixed>
     */
    public function ozet(TeknikServisKaydi $servis, array $taslak = []): array
    {
        $toplamTutar = $this->tutar($taslak['toplam_tutar'] ?? $servis->toplam_tutar ?? 0);
        $paraBirimi = strtoupper((string) ($taslak['para_birimi'] ?? $servis->tahsilat_para_birimi ?? $servis->cari?->para_birimi ?? 'TRY'));

        $plan = $this->sonPlan($servis);
        $teknikTahsilatlar = $this->teknikTahsilatlar($servis);
        $teknikTahsilatToplami = $this->tutar($teknikTahsilatlar->sum('tutar'));
        $servisOdenenToplami = $this->tutar($servis->odenen_tutar ?? 0);
        $tahsilatToplami = max($teknikTahsilatToplami, $servisOdenenToplami);

        $planPesinatTutar = $plan ? $this->tutar($plan->pesinat_tutari) : 0.0;
        $planKapsamTutar = $plan ? $this->tutar($plan->planlanan_tutar) : 0.0;
        $planKalanTutar = $plan && in_array((string) $plan->durum, self::ACIK_PLAN_DURUMLARI, true)
            ? $this->tutar($plan->kalan_tutar)
            : 0.0;
        $planOdenenTutar = $plan ? $this->tutar($plan->odenen_tutar) : 0.0;
        $servisBakiyeTutar = max(0.0, round($toplamTutar - $tahsilatToplami, 2));
        $kapsananNakitTutar = max($tahsilatToplami, $planPesinatTutar);
        $plansizKalanTutar = max(0.0, round($toplamTutar - ($kapsananNakitTutar + $planKapsamTutar), 2));
        $finansalAcikTutar = $plan ? $planKalanTutar : $servisBakiyeTutar;

        return [
            'servis' => $servis,
            'toplam_tutar' => $toplamTutar,
            'para_birimi' => $paraBirimi,
            'teknik_tahsilat_toplami' => $teknikTahsilatToplami,
            'servis_odenen_toplami' => $servisOdenenToplami,
            'tahsilat_toplami' => $tahsilatToplami,
            'servis_bakiye_tutar' => $servisBakiyeTutar,
            'finansal_acik_tutar' => $finansalAcikTutar,
            'plansiz_kalan_tutar' => $plansizKalanTutar,
            'plan' => $plan,
            'plan_kapsam_tutar' => $planKapsamTutar,
            'plan_pesinat_tutar' => $planPesinatTutar,
            'plan_kalan_tutar' => $planKalanTutar,
            'plan_odenen_tutar' => $planOdenenTutar,
            'taksitler' => $plan ? $plan->taksitler()->orderBy('sira_no')->get() : collect(),
            'plan_tahsilatlari' => $plan ? $this->planTahsilatlari($plan) : collect(),
            'teknik_tahsilatlar' => $teknikTahsilatlar,
            'durum' => $this->durum($plan, $finansalAcikTutar, $plansizKalanTutar),
        ];
    }

    /**
     * @param array<string,mixed> $taslak
     * @return array{engellendi:bool,uyari:bool,mesaj:string,ozet:array<string,mixed>}
     */
    public function teslimKontrolu(TeknikServisKaydi $servis, array $taslak = []): array
    {
        $ozet = $this->ozet($servis, $taslak);

        if ((float) $ozet['toplam_tutar'] <= 0.009) {
            return [
                'engellendi' => false,
                'uyari' => false,
                'mesaj' => '',
                'ozet' => $ozet,
            ];
        }

        if ((float) $ozet['plansiz_kalan_tutar'] > 0.009) {
            return [
                'engellendi' => true,
                'uyari' => false,
                'mesaj' => 'Teslim için açık tutar tahsil edilmeli veya ödeme planına bağlanmalıdır. Plansız kalan: '.$this->para((float) $ozet['plansiz_kalan_tutar'], (string) $ozet['para_birimi']).'.',
                'ozet' => $ozet,
            ];
        }

        if ($ozet['plan'] instanceof AlacakPlani && (float) $ozet['plan_kalan_tutar'] > 0.009) {
            return [
                'engellendi' => false,
                'uyari' => true,
                'mesaj' => 'Servis teslim edildi; açık alacak ödeme planı üzerinden takip edilecek. Plan bakiyesi: '.$this->para((float) $ozet['plan_kalan_tutar'], (string) $ozet['para_birimi']).'.',
                'ozet' => $ozet,
            ];
        }

        return [
            'engellendi' => false,
            'uyari' => false,
            'mesaj' => '',
            'ozet' => $ozet,
        ];
    }

    private function sonPlan(TeknikServisKaydi $servis): ?AlacakPlani
    {
        if (! $servis->exists) {
            return null;
        }

        return AlacakPlani::query()
            ->where('firma_id', (int) $servis->firma_id)
            ->where('kaynak_turu', 'teknik_servis')
            ->where('kaynak_id', (int) $servis->getKey())
            ->whereIn('durum', self::PLAN_OZET_DURUMLARI)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return Collection<int,TeknikServisTahsilati>
     */
    private function teknikTahsilatlar(TeknikServisKaydi $servis): Collection
    {
        if (! $servis->exists) {
            return collect();
        }

        return $servis->tahsilatlar()
            ->where('durum', 'aktif')
            ->orderByDesc('tarih')
            ->orderByDesc('id')
            ->limit(20)
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

    private function tutar(mixed $deger): float
    {
        return round((float) str_replace(',', '.', (string) $deger), 2);
    }

    private function para(float $tutar, string $paraBirimi): string
    {
        return number_format($tutar, 2, ',', '.').' '.$paraBirimi;
    }
}
